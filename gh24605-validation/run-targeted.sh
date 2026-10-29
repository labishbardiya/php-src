#!/usr/bin/env bash
# Run the GH-24605 isolated PHPTs in three independent repetitions.
# Usage: bash gh24605-validation/run-targeted.sh /path/to/php-src /path/to/output
set -u
ROOT="${1:-.}"
OUT="${2:-$HOME/gh24605-validation-results}"
cd "$ROOT" || exit 2
ROOT="$(pwd -P)"
mkdir -p "$OUT"
OUT="$(cd "$OUT" && pwd -P)"

if [ ! -x sapi/cli/php ] || [ ! -f run-tests.php ]; then
  echo "Missing compiled PHP binary or test runner in $ROOT; build PHP first" >&2
  exit 2
fi
{
  printf 'UTC: '; date -u '+%Y-%m-%dT%H:%M:%SZ'
  printf 'Repository: %s\n' "$ROOT"
  printf 'HEAD: '; git rev-parse HEAD
  printf 'Branch: '; git branch --show-current
  printf 'PHP: '; sapi/cli/php -n -v | head -n 1
  printf 'Hardware: '; uname -a
} > "$OUT/environment.txt"

printf 'test\trun\tresult\texit_code\tlog\n' > "$OUT/results.tsv"
FAILED=0
for file in \
  ext/standard/tests/general_functions/gh24605_print_r_stack_guard_resilience.phpt \
  ext/standard/tests/serialize/gh24605_unserialize_stack_guard_resilience.phpt
do
  if [ ! -f "$file" ]; then
    echo "Required PHPT missing: $file" >&2
    exit 2
  fi
  testname="$(basename "$file" .phpt)"
  for repetition in 1 2 3; do
    logfile="$OUT/${testname}-run${repetition}.log"
    if NO_INTERACTION=1 make test TESTS="$file" > "$logfile" 2>&1; then
      rc=0
    else
      rc=$?
    fi
    if [ "$rc" -eq 0 ] && grep -q '^PASS GH-24605:' "$logfile"; then
      result=PASS
    else
      result=FAIL_OR_SKIP
      FAILED=1
    fi
    printf '%s\t%s\t%s\t%s\t%s\n' "$testname" "$repetition" "$result" "$rc" "$logfile" >> "$OUT/results.tsv"
    printf '%s, repetition %s: %s (exit %s)\n' "$testname" "$repetition" "$result" "$rc"
    if [ "$result" != PASS ]; then
      tail -n 24 "$logfile"
    fi
  done
done
printf 'Evidence directory: %s\n' "$OUT"
exit "$FAILED"
