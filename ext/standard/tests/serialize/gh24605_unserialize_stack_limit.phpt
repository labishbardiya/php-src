--TEST--
GH-24605: unserialize() guards native stack before max_depth
--SKIPIF--
<?php
if (ini_get('zend.max_allowed_stack_size') === false) die('skip No stack limit support');
if (getenv('SKIP_ASAN')) die('skip ASAN requires adjusted stack limits');
?>
--INI--
zend.max_allowed_stack_size=256K
unserialize_max_depth=0
--FILE--
<?php
$depth = 3000;
$input = str_repeat('a:1:{i:0;', $depth) . 'i:0;' . str_repeat('}', $depth);
try {
    unserialize($input, ['max_depth' => 0]);
    echo "unserialize: no error\n";
} catch (Error $e) {
    echo "unserialize: guarded\n";
}
?>
--EXPECT--
unserialize: guarded
