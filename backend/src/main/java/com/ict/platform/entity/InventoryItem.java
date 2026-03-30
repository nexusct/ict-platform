package com.ict.platform.entity;

import jakarta.persistence.*;
import lombok.*;

import java.math.BigDecimal;

@Entity
@Table(name = "inventory_items")
@Data
@EqualsAndHashCode(callSuper = true)
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class InventoryItem extends BaseEntity {

    @Column(nullable = false)
    private String name;

    @Column(unique = true)
    private String sku;

    @Column(columnDefinition = "TEXT")
    private String description;

    @Column(nullable = false)
    private String category;

    @Column(name = "unit_cost", precision = 12, scale = 2)
    private BigDecimal unitCost;

    @Column(name = "unit_price", precision = 12, scale = 2)
    private BigDecimal unitPrice;

    @Column(name = "quantity_on_hand")
    @Builder.Default
    private Integer quantityOnHand = 0;

    @Column(name = "quantity_reserved")
    @Builder.Default
    private Integer quantityReserved = 0;

    @Column(name = "reorder_point")
    private Integer reorderPoint;

    @Column(name = "reorder_quantity")
    private Integer reorderQuantity;

    @Column(name = "max_stock_level")
    private Integer maxStockLevel;

    @Column(name = "unit_of_measure")
    private String unitOfMeasure;

    @Column(name = "supplier_id")
    private Long supplierId;

    @Column(name = "supplier_part_number")
    private String supplierPartNumber;

    @Column(name = "warehouse_location")
    private String warehouseLocation;

    @Column(name = "zoho_books_item_id")
    private String zohoBooksItemId;

    @Enumerated(EnumType.STRING)
    @Builder.Default
    private ItemStatus status = ItemStatus.ACTIVE;

    @Column(name = "is_serialized")
    @Builder.Default
    private Boolean serialized = false;

    @Column(name = "weight_kg", precision = 8, scale = 3)
    private BigDecimal weightKg;

    public enum ItemStatus {
        ACTIVE, INACTIVE, DISCONTINUED
    }
}
