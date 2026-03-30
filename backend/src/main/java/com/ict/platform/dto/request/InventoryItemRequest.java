package com.ict.platform.dto.request;

import com.ict.platform.entity.InventoryItem;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import lombok.Data;

import java.math.BigDecimal;

@Data
public class InventoryItemRequest {

    @NotBlank(message = "Item name is required")
    private String name;

    private String sku;

    private String description;

    @NotBlank(message = "Category is required")
    private String category;

    private BigDecimal unitCost;

    private BigDecimal unitPrice;

    private Integer quantityOnHand;

    private Integer reorderPoint;

    private Integer reorderQuantity;

    private Integer maxStockLevel;

    private String unitOfMeasure;

    private Long supplierId;

    private String supplierPartNumber;

    private String warehouseLocation;

    private InventoryItem.ItemStatus status;

    private Boolean serialized;
}
