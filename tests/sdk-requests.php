<?php

// Standalone regression: exercise AWS request construction through mock handlers only.

use Aws\MockHandler;
use Aws\Polly\PollyClient;
use Aws\Result;
use Aws\ResultInterface;
use Aws\S3\S3Client;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

$autoload_path = getenv( 'AWS_POLLY_VENDOR_AUTOLOAD' );
$autoload_path = $autoload_path ? $autoload_path : __DIR__ . '/../plugin-dir/vendor/autoload.php';
require $autoload_path;

function assert_same( $expected, $actual, string $message ): void {
	if ( $expected !== $actual ) {
		throw new RuntimeException(
			sprintf(
				"%s\nExpected: %s\nActual: %s",
				$message,
				var_export( $expected, true ),
				var_export( $actual, true )
			)
		);
	}
}

function assert_true( bool $actual, string $message ): void {
	assert_same( true, $actual, $message );
}

function assert_instance_of( string $expected, $actual, string $message ): void {
	assert_true( $actual instanceof $expected, $message );
}

$credentials = array(
	'key'    => 'test-access-key',
	'secret' => 'test-secret-key',
);

$polly_handler = new MockHandler(
	array(
		new Result(
			array(
				'AudioStream' => Utils::streamFor( 'mock-mp3' ),
				'ContentType' => 'audio/mpeg',
				'RequestCharacters' => 12,
			)
		),
	)
);
$polly         = new PollyClient(
	array(
		'credentials' => $credentials,
		'endpoint'    => 'https://polly.mock.invalid',
		'handler'     => $polly_handler,
		'region'      => 'us-east-1',
		'version'     => 'latest',
	)
);

$polly_promise = $polly->synthesizeSpeechAsync(
	array(
		'Engine'       => 'standard',
		'OutputFormat' => 'mp3',
		'Text'         => 'Hello Polly!',
		'TextType'     => 'text',
		'VoiceId'      => 'Joanna',
	)
);
assert_instance_of( PromiseInterface::class, $polly_promise, 'Polly async execution must return a Guzzle promise.' );

$polly_result = $polly_promise->wait();
assert_instance_of( ResultInterface::class, $polly_result, 'Polly promise resolution must return an AWS result.' );
assert_instance_of( StreamInterface::class, $polly_result['AudioStream'], 'Polly audio must remain a PSR-7 stream.' );
assert_same( 'mock-mp3', (string) $polly_result['AudioStream'], 'Polly result stream contents must survive promise resolution.' );

$polly_command = $polly_handler->getLastCommand();
$polly_request = $polly_handler->getLastRequest();
$polly_body    = json_decode( (string) $polly_request->getBody(), true, 512, JSON_THROW_ON_ERROR );
assert_same( 'SynthesizeSpeech', $polly_command->getName(), 'Polly must construct the SynthesizeSpeech command.' );
assert_same( 'POST', $polly_request->getMethod(), 'Polly synthesis must use POST.' );
assert_same( '/v1/speech', $polly_request->getUri()->getPath(), 'Polly must target the speech endpoint.' );
assert_same( 'application/json', $polly_request->getHeaderLine( 'Content-Type' ), 'Polly must serialize JSON.' );
assert_same( 'Hello Polly!', $polly_body['Text'], 'Polly must serialize the requested text.' );
assert_same( 'Joanna', $polly_body['VoiceId'], 'Polly must serialize the selected voice.' );
assert_true( str_starts_with( $polly_request->getHeaderLine( 'Authorization' ), 'AWS4-HMAC-SHA256 ' ), 'Polly request construction must include SigV4 signing.' );
assert_same( 0, count( $polly_handler ), 'Polly must consume exactly one mocked response.' );

$s3_handler = new MockHandler(
	array(
		new Result( array( 'ETag' => '"mock-etag"' ) ),
		new Result( array( 'DeleteMarker' => true ) ),
	)
);
$s3         = new S3Client(
	array(
		'credentials'             => $credentials,
		'endpoint'                => 'https://s3.mock.invalid',
		'handler'                 => $s3_handler,
		'region'                  => 'us-east-1',
		'use_path_style_endpoint' => true,
		'version'                 => 'latest',
	)
);

$put_result = $s3->putObject(
	array(
		'Body'        => 'mock-audio',
		'Bucket'      => 'fixture-bucket',
		'ContentType' => 'audio/mpeg',
		'Key'         => 'audio/test file.mp3',
	)
);
assert_instance_of( ResultInterface::class, $put_result, 'S3 PutObject must return an AWS result.' );
assert_same( '"mock-etag"', $put_result['ETag'], 'S3 PutObject must expose the mocked result data.' );

$put_command = $s3_handler->getLastCommand();
$put_request = $s3_handler->getLastRequest();
assert_same( 'PutObject', $put_command->getName(), 'S3 must construct the PutObject command.' );
assert_same( 'PUT', $put_request->getMethod(), 'S3 PutObject must use PUT.' );
assert_same( '/fixture-bucket/audio/test%20file.mp3', $put_request->getUri()->getPath(), 'S3 must encode and retain the full object key.' );
assert_same( 'audio/mpeg', $put_request->getHeaderLine( 'Content-Type' ), 'S3 must preserve the requested content type.' );
assert_same( 'mock-audio', (string) $put_request->getBody(), 'S3 must serialize the provided object body.' );
assert_true( str_starts_with( $put_request->getHeaderLine( 'Authorization' ), 'AWS4-HMAC-SHA256 ' ), 'S3 PutObject construction must include SigV4 signing.' );

$delete_promise = $s3->deleteObjectAsync(
	array(
		'Bucket' => 'fixture-bucket',
		'Key'    => 'audio/test file.mp3',
	)
);
assert_instance_of( PromiseInterface::class, $delete_promise, 'S3 async deletion must return a Guzzle promise.' );

$delete_result = $delete_promise->wait();
assert_instance_of( ResultInterface::class, $delete_result, 'S3 deletion promise must resolve to an AWS result.' );
assert_same( true, $delete_result['DeleteMarker'], 'S3 DeleteObject must expose the mocked result data.' );

$delete_command = $s3_handler->getLastCommand();
$delete_request = $s3_handler->getLastRequest();
assert_same( 'DeleteObject', $delete_command->getName(), 'S3 must construct the DeleteObject command.' );
assert_same( 'DELETE', $delete_request->getMethod(), 'S3 DeleteObject must use DELETE.' );
assert_same( '/fixture-bucket/audio/test%20file.mp3', $delete_request->getUri()->getPath(), 'S3 deletion must target the exact encoded object key.' );
assert_true( str_starts_with( $delete_request->getHeaderLine( 'Authorization' ), 'AWS4-HMAC-SHA256 ' ), 'S3 DeleteObject construction must include SigV4 signing.' );
assert_same( 0, count( $s3_handler ), 'S3 must consume exactly two mocked responses.' );

echo "PASS: AWS SDK request construction, promises, and results\n";
