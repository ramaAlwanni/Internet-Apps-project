package com.example.inventory_service.grpc;

import com.example.inventory.grpc.*;
import com.example.inventory_service.service.InventoryBusinessService;
import io.grpc.Status;
import io.grpc.stub.StreamObserver;
import net.devh.boot.grpc.server.service.GrpcService;
import org.springframework.beans.factory.annotation.Autowired;

@GrpcService
public class InventoryGrpcService extends InventoryServiceGrpc.InventoryServiceImplBase {

    private final InventoryBusinessService inventoryBusinessService;

    @Autowired
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
        } catch (Exception e) {
            responseObserver.onError(Status.NOT_FOUND
                    .withDescription(e.getMessage())
                    .asRuntimeException());
        }
    }

    @Override
    public void reserveStock(ReserveRequest request, StreamObserver<ReserveResponse> responseObserver) {
        try {
            long productId = request.getProductId();
            int quantity = request.getQuantity();

            boolean reserved = inventoryBusinessService.reserveStock(productId, quantity);

            ReserveResponse response = ReserveResponse.newBuilder()
                    .setSuccess(reserved)
                    .setMessage(reserved ? "تم الحجز بنجاح" : "الكمية غير متوفرة")
                    .build();

            responseObserver.onNext(response);
            responseObserver.onCompleted();
        } catch (Exception e) {
            responseObserver.onError(Status.INTERNAL
                    .withDescription(e.getMessage())
                    .asRuntimeException());
        }
    }

    @Override
    public void rollbackStock(RollbackRequest request, StreamObserver<RollbackResponse> responseObserver) {
        try {
            long productId = request.getProductId();
            int quantity = request.getQuantity();

            inventoryBusinessService.rollbackStock(productId, quantity);

            RollbackResponse response = RollbackResponse.newBuilder()
                    .setSuccess(true)
                    .setMessage("تمت إعادة الكمية بنجاح")
                    .build();

            responseObserver.onNext(response);
            responseObserver.onCompleted();
        } catch (Exception e) {
            responseObserver.onError(Status.INTERNAL
                    .withDescription(e.getMessage())
                    .asRuntimeException());
        }
    }
}
