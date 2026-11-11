package com.example.inventory_service.grpc;

import com.example.inventory.grpc.*;
import com.example.inventory_service.service.InventoryBusinessService;
import io.grpc.Status;
import io.grpc.stub.StreamObserver;
import net.devh.boot.grpc.server.service.GrpcService;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

@GrpcService
public class InventoryGrpcService extends InventoryServiceGrpc.InventoryServiceImplBase {

    private static final Logger LOGGER = LoggerFactory.getLogger(InventoryGrpcService.class);

    private final InventoryBusinessService inventoryBusinessService;

    public InventoryGrpcService(InventoryBusinessService inventoryBusinessService) {
        this.inventoryBusinessService = inventoryBusinessService;
    }

    @Override
    public void getStockQuantity(StockRequest request, StreamObserver<StockResponse> responseObserver) {
        try {
            long productId = request.getProductId();
            int availableQuantity = inventoryBusinessService.getAvailableQuantity(productId);

            StockResponse response = StockResponse.newBuilder()
                    .setProductId(productId)
                    .setAvailableQuantity(availableQuantity)
                    .build();

            responseObserver.onNext(response);
            responseObserver.onCompleted();
        } catch (InventoryBusinessService.ProductNotFoundException exception) {
            responseObserver.onError(Status.NOT_FOUND.withDescription(exception.getMessage()).asRuntimeException());
        } catch (IllegalArgumentException exception) {
            responseObserver.onError(Status.INVALID_ARGUMENT.withDescription(exception.getMessage()).asRuntimeException());
        } catch (Exception exception) {
            responseObserver.onError(Status.INTERNAL.withDescription("Unable to read inventory.").asRuntimeException());
        }
    }

    @Override
    public void ensureProductStock(ReserveRequest request, StreamObserver<ReserveResponse> responseObserver) {
        try {
            inventoryBusinessService.ensureProductStock(
                    request.getProductId(),
                    request.getQuantity()
            );
            respond(responseObserver, true, "Inventory record is ready.");
        } catch (IllegalArgumentException exception) {
            responseObserver.onError(Status.INVALID_ARGUMENT.withDescription(exception.getMessage()).asRuntimeException());
        } catch (Exception exception) {
            responseObserver.onError(Status.INTERNAL.withDescription("Unable to initialize inventory.").asRuntimeException());
        }
    }

    @Override
    public void reserveStock(ReserveRequest request, StreamObserver<ReserveResponse> responseObserver) {
        try {
            long productId = request.getProductId();
            int quantity = request.getQuantity();

            InventoryBusinessService.ReservationResult result =
                    inventoryBusinessService.reserveStock(productId, quantity, request.getOrderRefId());
            respond(responseObserver, result.success(), result.message());
        } catch (InventoryBusinessService.ProductNotFoundException exception) {
            responseObserver.onError(Status.NOT_FOUND.withDescription(exception.getMessage()).asRuntimeException());
        } catch (IllegalArgumentException exception) {
            responseObserver.onError(Status.INVALID_ARGUMENT.withDescription(exception.getMessage()).asRuntimeException());
        } catch (Exception exception) {
            LOGGER.error(
                    "Unable to reserve inventory for product {} and reservation {}.",
                    request.getProductId(),
                    request.getOrderRefId(),
                    exception
            );
            responseObserver.onError(Status.INTERNAL.withDescription("Unable to reserve inventory.").asRuntimeException());
        }
    }

    @Override
    public void reserveStockBatch(ReserveBatchRequest request, StreamObserver<ReserveResponse> responseObserver) {
        try {
            var items = request.getItemsList().stream()
                    .map(item -> new InventoryBusinessService.StockReservationItem(
                            item.getProductId(),
                            item.getQuantity()
                    ))
                    .toList();

            InventoryBusinessService.ReservationResult result =
                    inventoryBusinessService.reserveStockBatch(request.getOrderRefId(), items);
            respond(responseObserver, result.success(), result.message());
        } catch (InventoryBusinessService.ProductNotFoundException exception) {
            responseObserver.onError(Status.NOT_FOUND.withDescription(exception.getMessage()).asRuntimeException());
        } catch (InventoryBusinessService.InsufficientStockException exception) {
            respond(responseObserver, false, exception.getMessage());
        } catch (IllegalArgumentException exception) {
            responseObserver.onError(Status.INVALID_ARGUMENT.withDescription(exception.getMessage()).asRuntimeException());
        } catch (Exception exception) {
            LOGGER.error(
                    "Unable to reserve inventory batch for reservation {}.",
                    request.getOrderRefId(),
                    exception
            );
            responseObserver.onError(Status.INTERNAL.withDescription("Unable to reserve inventory.").asRuntimeException());
        }
    }

    @Override
    public void commitReservation(ReserveRequest request, StreamObserver<ReserveResponse> responseObserver) {
        try {
            InventoryBusinessService.ReservationResult result =
                    inventoryBusinessService.commitReservation(request.getOrderRefId());
            respond(responseObserver, result.success(), result.message());
        } catch (IllegalArgumentException exception) {
            responseObserver.onError(Status.INVALID_ARGUMENT.withDescription(exception.getMessage()).asRuntimeException());
        } catch (Exception exception) {
            responseObserver.onError(Status.INTERNAL.withDescription("Unable to commit reservation.").asRuntimeException());
        }
    }

    @Override
    public void releaseReservation(ReserveRequest request, StreamObserver<ReserveResponse> responseObserver) {
        try {
            InventoryBusinessService.ReservationResult result =
                    inventoryBusinessService.releaseReservation(request.getOrderRefId());
            respond(responseObserver, result.success(), result.message());
        } catch (IllegalArgumentException exception) {
            responseObserver.onError(Status.INVALID_ARGUMENT.withDescription(exception.getMessage()).asRuntimeException());
        } catch (Exception exception) {
            responseObserver.onError(Status.INTERNAL.withDescription("Unable to release reservation.").asRuntimeException());
        }
    }

    @Override
    public void rollbackStock(RollbackRequest request, StreamObserver<RollbackResponse> responseObserver) {
        try {
            long productId = request.getProductId();
            int quantity = request.getQuantity();

            InventoryBusinessService.ReservationResult result =
                    inventoryBusinessService.rollbackStock(productId, quantity, request.getOrderRefId());
            RollbackResponse response = RollbackResponse.newBuilder()
                    .setSuccess(result.success())
                    .setMessage(result.message())
                    .build();
            responseObserver.onNext(response);
            responseObserver.onCompleted();
        } catch (IllegalArgumentException exception) {
            responseObserver.onError(Status.INVALID_ARGUMENT.withDescription(exception.getMessage()).asRuntimeException());
        } catch (Exception exception) {
            responseObserver.onError(Status.INTERNAL.withDescription("Unable to roll back inventory.").asRuntimeException());
        }
    }

    private static void respond(StreamObserver<ReserveResponse> responseObserver, boolean success, String message) {
        responseObserver.onNext(ReserveResponse.newBuilder().setSuccess(success).setMessage(message).build());
        responseObserver.onCompleted();
    }
}
