package com.ict.platform.repository;

import com.ict.platform.entity.InventoryItem;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;
import org.springframework.data.repository.query.Param;
import org.springframework.stereotype.Repository;

import java.util.List;
import java.util.Optional;

@Repository
public interface InventoryItemRepository extends JpaRepository<InventoryItem, Long> {

    Optional<InventoryItem> findBySku(String sku);

    boolean existsBySku(String sku);

    Page<InventoryItem> findByCategory(String category, Pageable pageable);

    Page<InventoryItem> findByStatus(InventoryItem.ItemStatus status, Pageable pageable);

    @Query("SELECT i FROM InventoryItem i WHERE " +
           "(:category IS NULL OR i.category = :category) AND " +
           "(:status IS NULL OR i.status = :status) AND " +
           "(:search IS NULL OR LOWER(i.name) LIKE LOWER(CONCAT('%', :search, '%')) " +
           "OR LOWER(i.sku) LIKE LOWER(CONCAT('%', :search, '%')))")
    Page<InventoryItem> findWithFilters(
            @Param("category") String category,
            @Param("status") InventoryItem.ItemStatus status,
            @Param("search") String search,
            Pageable pageable);

    @Query("SELECT i FROM InventoryItem i WHERE i.quantityOnHand <= i.reorderPoint AND i.status = 'ACTIVE'")
    List<InventoryItem> findItemsBelowReorderPoint();

    @Query("SELECT i FROM InventoryItem i WHERE i.quantityOnHand = 0 AND i.status = 'ACTIVE'")
    List<InventoryItem> findOutOfStockItems();

    List<InventoryItem> findBySupplierId(Long supplierId);

    Optional<InventoryItem> findByZohoBooksItemId(String zohoBooksItemId);
}
