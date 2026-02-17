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

.PHONY: snapshot
snapshot: vendor                                                                ## regenerate the snapshot of the generated middleware
	UPDATE_SNAPSHOTS=1 vendor/bin/phpunit --no-coverage --filter testGeneratedCodeMatchesSnapshot

# benchmarks need opcache and must not run under xdebug, otherwise the numbers are meaningless
PHPBENCH_OPTS = --php-config='{"opcache.enable_cli": 1, "xdebug.mode": "off", "memory_limit": "-1"}'

.PHONY: benchmark
benchmark: vendor                                                               ## run benchmarks
	vendor/bin/phpbench run tests/Benchmark $(PHPBENCH_OPTS) --report=default

.PHONY: benchmark-fast
benchmark-fast: vendor                                                          ## run only the fast benchmarks (our hydrators and the generated eventsauce mapper) with 1M objects
	vendor/bin/phpbench run tests/Benchmark $(PHPBENCH_OPTS) --iterations=3 --revs=1 --report='{"generator":"expression","cols":["benchmark","subject","mode","rstdev"]}' --filter='Benchmark\\(StackHydratorBench|GeneratedHydratorBench|GeneratedHydratorWithCryptographyBench|HydratorWithCryptographyBench|GeneratedEventSauceHydratorBench)::bench(Hydrate|Extract)1000000Objects$$'

.PHONY: benchmark-diff-test
benchmark-diff-test: vendor                                                          ## run benchmarks
	vendor/bin/phpbench run tests/Benchmark $(PHPBENCH_OPTS) --revs=1 --report=default --progress=none --tag=base
	vendor/bin/phpbench run tests/Benchmark $(PHPBENCH_OPTS) --revs=1 --report=diff --progress=none --ref=base


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
