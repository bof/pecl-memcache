--TEST--
failed session locking test
--SKIPIF--
<?php include 'connect.inc'; if (!MEMCACHE_HAVE_SESSION) print 'skip not compiled with session support'; else if (!function_exists('pcntl_fork')) print 'skip not compiled with pcntl_fork() support'; ?>
--FILE--
<?php

ob_start();

include 'connect.inc';

# purpose of this test is to check that ps_read_session() does the right thing
# in case it cannot lock the session.
#
# strategy is to open a session, then fork a child to do the actual testing.

ini_set('session.use_strict_mode', 0);
ini_set('memcache.session_redundancy', 2);
ini_set('session.save_handler', 'memcache');
ini_set('memcache.session_save_path', "tcp://$host:$port?udp_port=$udpPort, tcp://$host2:$port2?udp_port=$udpPort2");
ini_set('memcache.lock_timeout', 1); # actual value is doubled in ps_read_session() --> 2 seconds

# make sure no shit is leftover from previous test
$key = 'memcache_tests_session_lock';
$keylock = 'memcache_tests_session_lock.lock';
$mc = test_connect1(); $mc->delete($key); $mc->delete($keylock);
$mc = test_connect2(); $mc->delete($key); $mc->delete($keylock);

function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

function session_str()
{
	return strtr(var_export($_SESSION, true), "\n", " ");
}

$t1 = microtime_float();

$t2 = microtime_float();
printf("[%.06f] parent BEGIN\n", $t2 - $t1);
session_id($key);
session_start();
$_SESSION['test']=true;
$t2 = microtime_float();
printf("[%.06f] parent %s\n", $t2 - $t1, session_str());
session_write_close();

$pid = pcntl_fork();
if (!$pid) {
	ob_clean();
	ob_start();
	printf("[%.06f] child BEGIN\n", $t2 - $t1);
	usleep(250000);
	session_id($key);
	session_start();
	$t2 = microtime_float();
	printf("[%.06f] child %s\n", $t2 - $t1, session_str());
	session_write_close();
	$t2 = microtime_float();
	printf("[%.06f] child EXIT\n", $t2 - $t1);
	ob_flush();
	exit(0);
}

session_id($key);
session_start();
$t2 = microtime_float();
printf("[%.06f] parent %s\n", $t2 - $t1, session_str());
usleep(4000000);
$t2 = microtime_float();
printf("[%.06f] parent RESUME\n", $t2 - $t1);
session_write_close();
$t2 = microtime_float();
printf("[%.06f] parent EXIT\n", $t2 - $t1);

ob_flush();

?>
--EXPECTF--
[0.%f] child BEGIN
[0.%f] child array ( )
[0.%f] child EXIT
[0.%f] parent BEGIN
[0.%f] parent array ( )
[4.%f] parent RESUME
[4.%f] parent EXIT
