<?php

declare(strict_types=1);

require dirname(__DIR__, 5) . '/vendor/autoload.php';

use Grpc\ChannelCredentials;
use Inventory\InventoryServiceClient;
use Inventory\ReserveRequest;

[$script, $productId, $reservationId, $host] = $argv;

$client = new InventoryServiceClient($host, [
    'credentials' => ChannelCredentials::createInsecure(),
]);
$request = new ReserveRequest();
$request->setProductId((int) $productId);
$request->setQuantity(1);
$request->setOrderRefId($reservationId);
[$response, $status] = $client->ReserveStock(
    $request,
    [],
    ['timeout' => 5_000_000]
)->wait();

if ($status->code !== 0 || !$response instanceof \Inventory\ReserveResponse) {
    fwrite(STDERR, $status->details ?: "Inventory gRPC request failed ({$status->code}).");
    exit(1);
}

echo json_encode([
    'success' => $response->getSuccess(),
    'message' => $response->getMessage(),
    'reservation_id' => $reservationId,
], JSON_THROW_ON_ERROR);
