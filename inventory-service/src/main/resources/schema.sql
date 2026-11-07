CREATE TABLE IF NOT EXISTS inventory_items (
    product_id BIGINT NOT NULL PRIMARY KEY,
    available_quantity INT NOT NULL,
    CONSTRAINT chk_inventory_items_quantity CHECK (available_quantity >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reservations (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    status VARCHAR(16) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_reservations_status CHECK (status IN ('RESERVED', 'COMMITTED', 'RELEASED'))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reservation_items (
    reservation_id VARCHAR(64) NOT NULL,
    product_id BIGINT NOT NULL,
    quantity INT NOT NULL,
    released BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (reservation_id, product_id),
    CONSTRAINT chk_reservation_items_quantity CHECK (quantity > 0),
    CONSTRAINT fk_reservation_items_reservation
        FOREIGN KEY (reservation_id) REFERENCES reservations (id),
    CONSTRAINT fk_reservation_items_inventory
        FOREIGN KEY (product_id) REFERENCES inventory_items (product_id)
) ENGINE=InnoDB;
