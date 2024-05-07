# DEV Environment for AWS Polly WordPress plugin

## Requirements
Linux or WSL2, Make, Docker Compose

## Notice
Call all commands from root project directory.

## Installation

```bash
bash ./dev/init.sh && make docker.up
make connect.php 
composer install
```

Don't forget update your hosts file
`127.0.0.1 aws-polly.local`.

## Development
WP plugin directory `plugin-dir`.
