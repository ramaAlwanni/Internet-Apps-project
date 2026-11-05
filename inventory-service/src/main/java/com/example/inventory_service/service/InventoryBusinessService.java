package com.example.inventory_service.service;

import org.springframework.dao.EmptyResultDataAccessException;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;
import java.util.Map;

@Service
public class InventoryBusinessService {

    private final JdbcTemplate jdbcTemplate;

    public InventoryBusinessService(JdbcTemplate jdbcTemplate) {
        this.jdbcTemplate = jdbcTemplate;
    }

    @Transactional
    public int ensureProductStock(long productId, int initialQuantity) {
        validateProductId(productId);
        if (initialQuantity < 0) {
            throw new IllegalArgumentException("Initial quantity cannot be negative.");
        }

        jdbcTemplate.update(
                "INSERT IGNORE INTO inventory_items (product_id, available_quantity) VALUES (?, ?)",
                productId,
                initialQuantity
        );
        return getAvailableQuantity(productId);
    }

    @Transactional(readOnly = true)
    public int getAvailableQuantity(long productId) {
        validateProductId(productId);
        try {
            return jdbcTemplate.queryForObject(
                    "SELECT available_quantity FROM inventory_items WHERE product_id = ?",
                    Integer.class,
                    productId
            );
        } catch (EmptyResultDataAccessException exception) {
            throw new ProductNotFoundException(productId);
        }
    }

    @Transactional
    public ReservationResult reserveStock(long productId, int quantity, String reservationId) {
        validateProductId(productId);
        validateReservationId(reservationId);
        if (quantity <= 0) {
            throw new IllegalArgumentException("Quantity must be greater than zero.");
        }

        jdbcTemplate.update(
                "INSERT IGNORE INTO reservations (id, status) VALUES (?, 'RESERVED')",
                reservationId
        );
        String reservationStatus = jdbcTemplate.queryForObject(
                "SELECT status FROM reservations WHERE id = ? FOR UPDATE",
                String.class,
                reservationId
        );

        List<Map<String, Object>> existingItems = jdbcTemplate.queryForList(
                "SELECT quantity, released FROM reservation_items "
                        + "WHERE reservation_id = ? AND product_id = ?",
                reservationId,
                productId
        );
        if (!existingItems.isEmpty()) {
            Map<String, Object> existingItem = existingItems.get(0);
            int existingQuantity = ((Number) existingItem.get("quantity")).intValue();
            boolean released = (Boolean) existingItem.get("released");
            if (existingQuantity == quantity && !released
                    && ("RESERVED".equals(reservationStatus) || "COMMITTED".equals(reservationStatus))) {
                return ReservationResult.succeeded();
            }
            return ReservationResult.failure("Reservation does not match the previous request.");
        }

        if (!"RESERVED".equals(reservationStatus)) {
            return ReservationResult.failure("Reservation is no longer active.");
        }

        int availableQuantity;
        try {
            availableQuantity = jdbcTemplate.queryForObject(
                    "SELECT available_quantity FROM inventory_items WHERE product_id = ? FOR UPDATE",
                    Integer.class,
                    productId
            );
        } catch (EmptyResultDataAccessException exception) {
            throw new ProductNotFoundException(productId);
        }

        if (availableQuantity < quantity) {
            return ReservationResult.failure("Insufficient stock.");
        }

        jdbcTemplate.update(
                "UPDATE inventory_items SET available_quantity = available_quantity - ? WHERE product_id = ?",
                quantity,
                productId
        );
        jdbcTemplate.update(
                "INSERT INTO reservation_items (reservation_id, product_id, quantity) VALUES (?, ?, ?)",
                reservationId,
                productId,
                quantity
        );

        return ReservationResult.succeeded();
    }

    @Transactional
    public ReservationResult commitReservation(String reservationId) {
        validateReservationId(reservationId);
        String status = findReservationStatusForUpdate(reservationId);
        if (status == null) {
            return ReservationResult.failure("Reservation was not found.");
        }
        if ("COMMITTED".equals(status)) {
            return ReservationResult.succeeded();
        }
        if (!"RESERVED".equals(status)) {
            return ReservationResult.failure("Released reservations cannot be committed.");
        }

        jdbcTemplate.update(
                "UPDATE reservations SET status = 'COMMITTED', updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                reservationId
        );
        return ReservationResult.succeeded();
    }

    @Transactional
    public ReservationResult releaseReservation(String reservationId) {
        validateReservationId(reservationId);
        String status = findReservationStatusForUpdate(reservationId);
        if (status == null || "RELEASED".equals(status)) {
            return ReservationResult.succeeded();
        }
        if ("COMMITTED".equals(status)) {
            return ReservationResult.failure("Committed reservations cannot be released.");
        }

        List<Map<String, Object>> items = jdbcTemplate.queryForList(
                "SELECT product_id, quantity FROM reservation_items "
                        + "WHERE reservation_id = ? AND released = FALSE ORDER BY product_id FOR UPDATE",
                reservationId
        );
        for (Map<String, Object> item : items) {
            long productId = ((Number) item.get("product_id")).longValue();
            int quantity = ((Number) item.get("quantity")).intValue();
            jdbcTemplate.update(
                    "UPDATE inventory_items SET available_quantity = available_quantity + ? WHERE product_id = ?",
                    quantity,
                    productId
            );
            jdbcTemplate.update(
                    "UPDATE reservation_items SET released = TRUE WHERE reservation_id = ? AND product_id = ?",
                    reservationId,
                    productId
            );
        }
        jdbcTemplate.update(
                "UPDATE reservations SET status = 'RELEASED', updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                reservationId
        );
        return ReservationResult.succeeded();
    }

    @Transactional
    public ReservationResult rollbackStock(long productId, int quantity, String reservationId) {
        validateProductId(productId);
        validateReservationId(reservationId);
        if (quantity <= 0) {
            throw new IllegalArgumentException("Quantity must be greater than zero.");
        }

        String status = findReservationStatusForUpdate(reservationId);
        if (status == null || "RELEASED".equals(status)) {
            return ReservationResult.succeeded();
        }
        if (!"RESERVED".equals(status)) {
            return ReservationResult.failure("Committed reservations cannot be released.");
        }

        List<Map<String, Object>> items = jdbcTemplate.queryForList(
                "SELECT quantity, released FROM reservation_items "
                        + "WHERE reservation_id = ? AND product_id = ? FOR UPDATE",
                reservationId,
                productId
        );
        if (items.isEmpty()) {
            return ReservationResult.failure("Reservation item was not found.");
        }

        Map<String, Object> item = items.get(0);
        if (((Number) item.get("quantity")).intValue() != quantity) {
            return ReservationResult.failure("Rollback quantity does not match the reservation.");
        }
        if (!(Boolean) item.get("released")) {
            jdbcTemplate.update(
                    "UPDATE inventory_items SET available_quantity = available_quantity + ? WHERE product_id = ?",
                    quantity,
                    productId
            );
            jdbcTemplate.update(
                    "UPDATE reservation_items SET released = TRUE WHERE reservation_id = ? AND product_id = ?",
                    reservationId,
                    productId
            );
        }

        int unreleasedItems = jdbcTemplate.queryForObject(
                "SELECT COUNT(*) FROM reservation_items WHERE reservation_id = ? AND released = FALSE",
                Integer.class,
                reservationId
        );
        if (unreleasedItems == 0) {
            jdbcTemplate.update(
                    "UPDATE reservations SET status = 'RELEASED', updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                    reservationId
            );
        }
        return ReservationResult.succeeded();
    }

    private String findReservationStatusForUpdate(String reservationId) {
        List<String> statuses = jdbcTemplate.query(
                "SELECT status FROM reservations WHERE id = ? FOR UPDATE",
                (resultSet, rowNumber) -> resultSet.getString("status"),
                reservationId
        );
        return statuses.isEmpty() ? null : statuses.get(0);
    }

    private static void validateProductId(long productId) {
        if (productId <= 0) {
            throw new IllegalArgumentException("Product ID must be greater than zero.");
        }
    }

    private static void validateReservationId(String reservationId) {
        if (reservationId == null || reservationId.isBlank() || reservationId.length() > 64) {
            throw new IllegalArgumentException("A valid reservation ID is required.");
        }
    }

    public record ReservationResult(boolean success, String message) {
        static ReservationResult succeeded() {
            return new ReservationResult(true, "Success");
        }

        static ReservationResult failure(String message) {
            return new ReservationResult(false, message);
        }
    }

    public static class ProductNotFoundException extends RuntimeException {
        public ProductNotFoundException(long productId) {
            super("Product not found: " + productId);
        }
    }
}
