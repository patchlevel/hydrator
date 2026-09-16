help:                                                                           ## shows this help
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_\-\.]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

vendor: composer.lock
	composer install

.PHONY: phpcs-check
cs-check: vendor                                                                ## run phpcs
	vendor/bin/phpcs

.PHONY: cs
cs: vendor                                                                      ## run phpcs fixer
	vendor/bin/phpcbf || true
	vendor/bin/phpcs

.PHONY: phpstan
phpstan: vendor                                                                 ## run phpstan static code analyser
	vendor/bin/phpstan analyse --memory-limit=-1

.PHONY: phpstan-baseline
phpstan-baseline: vendor                                                        ## run phpstan static code analyser
	vendor/bin/phpstan analyse --generate-baseline --memory-limit=-1

.PHONY: phpunit
phpunit: vendor                                                                 ## run phpunit tests
	XDEBUG_MODE=coverage vendor/bin/phpunit

.PHONY: infection
infection: vendor                                                               ## run infection
	XDEBUG_MODE=coverage php -d memory_limit=312M vendor/bin/infection --threads=3

.PHONY: static
static: phpstan cs                                               				## run static analysers

test: phpunit                                                                   ## run tests

.PHONY: benchmark
benchmark: vendor                                                               ## run benchmarks
	vendor/bin/phpbench run tests/Benchmark --report=default

.PHONY: benchmark-diff-test
benchmark-diff-test: vendor                                                          ## run benchmarks
	vendor/bin/phpbench run tests/Benchmark --revs=1 --report=default --progress=none --tag=base
	vendor/bin/phpbench run tests/Benchmark --revs=1 --report=diff --progress=none --ref=base


.PHONY: docs
docs: docs-extract-php docs-php-lint docs-phpcs docs-inject-php                  ## check and format docs code

.PHONY: docs-extract-php
docs-extract-php: vendor
	bin/docs-extract-php-code

.PHONY: docs-inject-php
docs-inject-php: vendor
	bin/docs-inject-php-code

.PHONY: docs-format
docs-format: docs-phpcs docs-inject-php                                         ## format docs

.PHONY: docs-php-lint
docs-php-lint: docs-extract-php                                                 ## lint docs code
	php -l docs_php/*.php | grep -E 'Parse error|Fatal error' || true

.PHONY: docs-phpcs
docs-phpcs: docs-extract-php
	vendor/bin/phpcbf docs_php --exclude=SlevomatCodingStandard.TypeHints.DeclareStrictTypes,SlevomatCodingStandard.ControlStructures.EarlyExit || true

.PHONY: dev
dev: static test                                                                ## run dev tools
