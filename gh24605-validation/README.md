# GH-24605: independent, repeatable regression experiment

**Experiment only.** This branch is not an alternative upstream PR to #25014. It starts from the fork's accepted-var_export development history and adds the two missing native guards plus independent PHPT coverage. Code is not yet certified on all platforms.

## Why

Official upstream #24671/commit afe5635c already fixed `var_export()`. PR #25014 proposes both remaining guards but its combined 3,000-level / 512K test fails on Linux x32 in GitHub Actions run 40741036716: `print_r()` exhausts 128 MiB while formatting the deeply indented representation. This experiment isolates the cases and lowers the stack ceiling to 128K for `print_r()` and 256K for `unserialize()`, both at 5,000 levels.

`Zend/zend.c` also short-circuits `print_hash()` after an exception instead of adding text on the unwinding path. This modification is a hypothesis to validate, not a proven performance benefit.

## Compile on macOS (requires PHP build dependencies)

```sh
cd ~
git clone --depth 1 --single-branch --branch experiment/gh24605-exception-safe-coverage \
  https://github.com/labishbardiya/php-src.git php-gh24605-experiment
cd ~/php-gh24605-experiment
export PATH="$(brew --prefix bison)/bin:$PATH"
./buildconf --force
./configure --disable-all --enable-cli --enable-debug
make -j4
bash gh24605-validation/run-targeted.sh . "$HOME/gh24605-experiment-results"
```

The script runs each PHPT three times and captures stdout/stderr, exit codes, and environment. PASS means all assertions in the PHPT ran successfully; a skip is **not** counted as a pass.

## Fair comparison against PR #25014

Build the other contributor's exact code in a **separate** directory. Don't modify their fork or post a duplicate upstream PR.

```sh
cd ~
git clone --depth 1 --single-branch --branch fix/stack-limit-var-export-print-r \
  https://github.com/Volkopat/php-src.git php-gh24605-pr25014
cd ~/php-gh24605-pr25014
export PATH="$(brew --prefix bison)/bin:$PATH"
./buildconf --force
./configure --disable-all --enable-cli --enable-debug
make -j4
cp ~/php-gh24605-experiment/ext/standard/tests/general_functions/gh24605_print_r_stack_guard_resilience.phpt \
  ext/standard/tests/general_functions/
cp ~/php-gh24605-experiment/ext/standard/tests/serialize/gh24605_unserialize_stack_guard_resilience.phpt \
  ext/standard/tests/serialize/
bash ~/php-gh24605-experiment/gh24605-validation/run-targeted.sh . "$HOME/gh24605-pr25014-results"
```

The two code versions may differ in upstream base revision; record both Git SHAs and do not claim causality from a comparison without controlling for that difference.

## Verification gates

1. Both binaries build with no compiler errors or warnings introduced by our changes.
2. Six experimental targeted runs pass without exceptions other than the expected stack-limit Error.
3. The reference PR code is tested against the same PHPT files, with logs retained.
4. A macOS PASS is local evidence, **not proof of a Linux x32 CI PASS**.
5. Only share a positive comparison or upstream review comment after inspecting logs.
6. Verify the behavior with debug, ZTS, and sanitizer builds before making a portability claim.

Note that very deep `print_r()` output can consume memory roughly quadratically with depth because of indentation. This experimental stack ceiling is deliberately low so the guard fires before huge output is allocated.
