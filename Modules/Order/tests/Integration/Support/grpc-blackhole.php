<?php

declare(strict_types=1);

$server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
if ($server === false) {
    fwrite(STDERR, "{$errorMessage} ({$errorCode})");
    exit(1);
}

$address = stream_socket_get_name($server, false);
$port = (int) substr($address, strrpos($address, ':') + 1);
echo "PORT:{$port}\n";
flush();

while (true) {
    $connection = @stream_socket_accept($server, 1);
    if ($connection !== false) {
        sleep(10);
        fclose($connection);
    }
}
