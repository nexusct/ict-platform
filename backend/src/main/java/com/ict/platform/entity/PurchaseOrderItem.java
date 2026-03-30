package com.ict.platform.entity;

import jakarta.persistence.*;
import lombok.*;

import java.math.BigDecimal;

@Entity
@Table(name = "purchase_order_items")
@Data
@EqualsAndHashCode(callSuper = true)
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class PurchaseOrderItem extends BaseEntity {

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "purchase_order_id", nullable = false)
    private PurchaseOrder purchaseOrder;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "inventory_item_id")
    private InventoryItem inventoryItem;

    @Column(nullable = false)
    private String description;

    @Column(nullable = false)
    private Integer quantity;

    @Column(name = "unit_cost", precision = 12, scale = 2, nullable = false)
    private BigDecimal unitCost;

    @Column(name = "total_cost", precision = 15, scale = 2)
    private BigDecimal totalCost;

    @Column(name = "quantity_received")
    @Builder.Default
    private Integer quantityReceived = 0;

    @Column(name = "unit_of_measure")
    private String unitOfMeasure;

    @Column(name = "part_number")
    private String partNumber;
}
