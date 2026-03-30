package com.ict.platform.entity;

import jakarta.persistence.*;
import lombok.*;

import java.math.BigDecimal;
import java.time.LocalDate;
import java.util.ArrayList;
import java.util.List;

@Entity
@Table(name = "purchase_orders")
@Data
@EqualsAndHashCode(callSuper = true)
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class PurchaseOrder extends BaseEntity {

    @Column(name = "po_number", unique = true, nullable = false)
    private String poNumber;

    @Column(name = "project_id")
    private Long projectId;

    @Column(name = "supplier_id")
    private Long supplierId;

    @Column(name = "supplier_name")
    private String supplierName;

    @Enumerated(EnumType.STRING)
    @Builder.Default
    private POStatus status = POStatus.DRAFT;

    @Column(name = "order_date")
    private LocalDate orderDate;

    @Column(name = "expected_delivery_date")
    private LocalDate expectedDeliveryDate;

    @Column(name = "actual_delivery_date")
    private LocalDate actualDeliveryDate;

    @Column(name = "subtotal", precision = 15, scale = 2)
    @Builder.Default
    private BigDecimal subtotal = BigDecimal.ZERO;

    @Column(name = "tax_amount", precision = 15, scale = 2)
    @Builder.Default
    private BigDecimal taxAmount = BigDecimal.ZERO;

    @Column(name = "shipping_cost", precision = 15, scale = 2)
    @Builder.Default
    private BigDecimal shippingCost = BigDecimal.ZERO;

    @Column(name = "total_amount", precision = 15, scale = 2)
    @Builder.Default
    private BigDecimal totalAmount = BigDecimal.ZERO;

    @Column(name = "currency_code")
    @Builder.Default
    private String currencyCode = "USD";

    @Column(columnDefinition = "TEXT")
    private String notes;

    @Column(name = "approved_by")
    private Long approvedBy;

    @Column(name = "zoho_books_po_id")
    private String zohoBooksPOId;

    @Column(name = "shipping_address", columnDefinition = "TEXT")
    private String shippingAddress;

    @Column(name = "payment_terms")
    private String paymentTerms;

    @OneToMany(mappedBy = "purchaseOrder", cascade = CascadeType.ALL, orphanRemoval = true)
    @Builder.Default
    private List<PurchaseOrderItem> items = new ArrayList<>();

    public enum POStatus {
        DRAFT, SUBMITTED, APPROVED, ORDERED, PARTIALLY_RECEIVED, RECEIVED, CANCELLED
    }
}
