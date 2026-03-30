package com.ict.platform.service;

import com.ict.platform.dto.request.InventoryItemRequest;
import com.ict.platform.entity.InventoryItem;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;

import java.util.List;

public interface InventoryService {

    InventoryItem createItem(InventoryItemRequest request);

    InventoryItem updateItem(Long id, InventoryItemRequest request);

    InventoryItem getItemById(Long id);

    Page<InventoryItem> getAllItems(String category, InventoryItem.ItemStatus status, String search, Pageable pageable);

    void deleteItem(Long id);

    InventoryItem adjustStock(Long id, int quantityChange, String reason);

    List<InventoryItem> getLowStockItems();

    List<InventoryItem> getOutOfStockItems();

    void syncInventoryToZoho(Long id);
}
