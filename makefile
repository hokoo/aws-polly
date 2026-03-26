docker.up:
	docker-compose -p aws-polly-wp up -d

docker.stop:
	docker-compose -p aws-polly-wp stop

docker.down:
	docker-compose -p aws-polly-wp down

docker.build.php:
	docker-compose -p aws-polly-wp up -d --build php

php.log:
	docker-compose -p aws-polly-wp exec php sh -c "grc tail -f /var/log/php/error.log"

clear.all:
	bash ./install/clear.sh

connect.php:
	docker-compose -p aws-polly-wp exec php bash

connect.nginx:
	docker-compose -p aws-polly-wp exec nginx sh

connect.php.root:
	docker-compose -p aws-polly-wp exec --user=root php bash
