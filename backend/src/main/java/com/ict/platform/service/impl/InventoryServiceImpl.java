package com.ict.platform.service.impl;

import com.ict.platform.dto.request.InventoryItemRequest;
import com.ict.platform.entity.InventoryItem;
import com.ict.platform.entity.SyncQueue;
import com.ict.platform.exception.BusinessException;
import com.ict.platform.exception.ConflictException;
import com.ict.platform.exception.ResourceNotFoundException;
import com.ict.platform.repository.InventoryItemRepository;
import com.ict.platform.repository.SyncQueueRepository;
import com.ict.platform.service.InventoryService;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;

@Service
@RequiredArgsConstructor
@Slf4j
public class InventoryServiceImpl implements InventoryService {

    private final InventoryItemRepository inventoryItemRepository;
    private final SyncQueueRepository syncQueueRepository;

    @Override
    @Transactional
    public InventoryItem createItem(InventoryItemRequest request) {
        if (request.getSku() != null && inventoryItemRepository.existsBySku(request.getSku())) {
            throw new ConflictException("SKU already exists: " + request.getSku());
        }

        InventoryItem item = InventoryItem.builder()
                .name(request.getName())
                .sku(request.getSku())
                .description(request.getDescription())
                .category(request.getCategory())
                .unitCost(request.getUnitCost())
                .unitPrice(request.getUnitPrice())
                .quantityOnHand(request.getQuantityOnHand() != null ? request.getQuantityOnHand() : 0)
                .reorderPoint(request.getReorderPoint())
                .reorderQuantity(request.getReorderQuantity())
                .maxStockLevel(request.getMaxStockLevel())
                .unitOfMeasure(request.getUnitOfMeasure())
                .supplierId(request.getSupplierId())
                .supplierPartNumber(request.getSupplierPartNumber())
                .warehouseLocation(request.getWarehouseLocation())
                .status(request.getStatus() != null ? request.getStatus() : InventoryItem.ItemStatus.ACTIVE)
                .serialized(request.getSerialized() != null ? request.getSerialized() : false)
                .build();

        item = inventoryItemRepository.save(item);
        log.info("Created inventory item: {} ({})", item.getName(), item.getSku());

        queueSync(item.getId(), "inventory_item", SyncQueue.SyncAction.CREATE, SyncQueue.ZohoService.BOOKS);

        return item;
    }

    @Override
    @Transactional
    public InventoryItem updateItem(Long id, InventoryItemRequest request) {
        InventoryItem item = getItemById(id);

        if (request.getSku() != null && !request.getSku().equals(item.getSku())
                && inventoryItemRepository.existsBySku(request.getSku())) {
            throw new ConflictException("SKU already exists: " + request.getSku());
        }

        item.setName(request.getName());
        if (request.getSku() != null) item.setSku(request.getSku());
        item.setDescription(request.getDescription());
        item.setCategory(request.getCategory());
        item.setUnitCost(request.getUnitCost());
        item.setUnitPrice(request.getUnitPrice());
        item.setReorderPoint(request.getReorderPoint());
        item.setReorderQuantity(request.getReorderQuantity());
        item.setMaxStockLevel(request.getMaxStockLevel());
        item.setUnitOfMeasure(request.getUnitOfMeasure());
        item.setSupplierId(request.getSupplierId());
        item.setSupplierPartNumber(request.getSupplierPartNumber());
        item.setWarehouseLocation(request.getWarehouseLocation());
        if (request.getStatus() != null) item.setStatus(request.getStatus());
        if (request.getSerialized() != null) item.setSerialized(request.getSerialized());

        item = inventoryItemRepository.save(item);
        log.info("Updated inventory item: {} ({})", item.getName(), item.getSku());

        queueSync(item.getId(), "inventory_item", SyncQueue.SyncAction.UPDATE, SyncQueue.ZohoService.BOOKS);

        return item;
    }

    @Override
    @Transactional(readOnly = true)
    public InventoryItem getItemById(Long id) {
        return inventoryItemRepository.findById(id)
                .orElseThrow(() -> new ResourceNotFoundException("Inventory item not found: " + id));
    }

    @Override
    @Transactional(readOnly = true)
    public Page<InventoryItem> getAllItems(String category, InventoryItem.ItemStatus status, String search, Pageable pageable) {
        return inventoryItemRepository.findWithFilters(category, status, search, pageable);
    }

    @Override
    @Transactional
    public void deleteItem(Long id) {
        InventoryItem item = getItemById(id);
        inventoryItemRepository.delete(item);
        log.info("Deleted inventory item: {}", id);

        queueSync(id, "inventory_item", SyncQueue.SyncAction.DELETE, SyncQueue.ZohoService.BOOKS);
    }

    @Override
    @Transactional
    public InventoryItem adjustStock(Long id, int quantityChange, String reason) {
        InventoryItem item = getItemById(id);
        int newQuantity = item.getQuantityOnHand() + quantityChange;
        if (newQuantity < 0) {
            throw new BusinessException("Insufficient stock. Available: " + item.getQuantityOnHand());
        }
        item.setQuantityOnHand(newQuantity);
        item = inventoryItemRepository.save(item);
        log.info("Adjusted stock for item {}: {} (reason: {})", id, quantityChange, reason);

        queueSync(id, "inventory_item", SyncQueue.SyncAction.UPDATE, SyncQueue.ZohoService.BOOKS);

        return item;
    }

    @Override
    @Transactional(readOnly = true)
    public List<InventoryItem> getLowStockItems() {
        return inventoryItemRepository.findItemsBelowReorderPoint();
    }

    @Override
    @Transactional(readOnly = true)
    public List<InventoryItem> getOutOfStockItems() {
        return inventoryItemRepository.findOutOfStockItems();
    }

    @Override
    @Transactional
    public void syncInventoryToZoho(Long id) {
        getItemById(id);
        queueSync(id, "inventory_item", SyncQueue.SyncAction.UPDATE, SyncQueue.ZohoService.BOOKS);
        log.info("Queued sync for inventory item: {}", id);
    }

    private void queueSync(Long entityId, String entityType, SyncQueue.SyncAction action, SyncQueue.ZohoService service) {
        SyncQueue syncItem = SyncQueue.builder()
                .entityType(entityType)
                .entityId(entityId)
                .action(action)
                .zohoService(service)
                .priority(5)
                .build();
        syncQueueRepository.save(syncItem);
    }
}
