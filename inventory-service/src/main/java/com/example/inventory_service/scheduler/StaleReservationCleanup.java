package com.example.inventory_service.scheduler;

import com.example.inventory_service.service.InventoryBusinessService;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.scheduling.annotation.Scheduled;
import org.springframework.stereotype.Component;

/**
 * Safety net for the case Raghad raised: Laravel (or the gRPC link) dies after
 * the warehouse reserved stock but before release/commit reaches it. The
 * caller normally releases with the same reservation_id; this job cleans up
 * the rare reservation that no caller can reach any more.
 */
@Component
public class StaleReservationCleanup {

    private static final Logger LOGGER = LoggerFactory.getLogger(StaleReservationCleanup.class);

    private final InventoryBusinessService inventoryBusinessService;

    @Value("${inventory.reservations.stale-after-minutes:10}")
    private int staleAfterMinutes;

    @Value("${inventory.reservations.cleanup-batch-size:200}")
    private int batchSize;

    public StaleReservationCleanup(InventoryBusinessService inventoryBusinessService) {
        this.inventoryBusinessService = inventoryBusinessService;
    }

    @Scheduled(
            initialDelayString = "${inventory.reservations.cleanup-initial-delay-ms:10000}",
            fixedDelayString = "${inventory.reservations.cleanup-interval-ms:60000}"
    )
    public void releaseStaleReservations() {
        try {
            int released = inventoryBusinessService.releaseStaleReservations(
                    staleAfterMinutes,
                    batchSize
            );
            if (released > 0) {
                LOGGER.info(
                        "Released {} stale RESERVED reservation(s) older than {} minute(s).",
                        released,
                        staleAfterMinutes
                );
            }
        } catch (Exception exception) {
            // A failed pass must not stop later passes.
            LOGGER.error("Stale reservation cleanup failed.", exception);
        }
    }
}
