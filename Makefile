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
	vendor/bin/phpstan analyse

.PHONY: phpstan-baseline
phpstan-baseline: vendor                                                        ## run phpstan static code analyser
	vendor/bin/phpstan analyse --generate-baseline

.PHONY: psalm
psalm: vendor                                                                   ## run psalm static code analyser
	vendor/bin/psalm

.PHONY: psalm-baseline
psalm-baseline: vendor                                                          ## run psalm static code analyser
	vendor/bin/psalm --update-baseline --set-baseline=baseline.xml

.PHONY: phpunit
phpunit: vendor                                                                 ## run phpunit tests
	XDEBUG_MODE=coverage vendor/bin/phpunit

.PHONY: infection
infection: vendor                                                               ## run infection
	vendor/bin/infection

.PHONY: static
static: psalm phpstan phpcs-check                                               ## run static analyser

test: phpunit                                                                   ## run tests

.PHONY: benchmark
benchmark: vendor                                                               ## run benchmarks
	vendor/bin/phpbench run tests/Benchmark --report=default

.PHONY: benchmark-diff-test
benchmark-diff-test: vendor                                                          ## run benchmarks
	vendor/bin/phpbench run tests/Benchmark --revs=1 --report=default --progress=none --tag=base
	vendor/bin/phpbench run tests/Benchmark --revs=1 --report=diff --progress=none --ref=base


.PHONY: dev
dev: static test                                                                ## run dev tools
