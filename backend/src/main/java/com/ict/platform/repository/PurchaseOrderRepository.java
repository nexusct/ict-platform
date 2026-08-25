package com.ict.platform.repository;

import com.ict.platform.entity.PurchaseOrder;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;
import org.springframework.data.repository.query.Param;
import org.springframework.stereotype.Repository;

import java.util.List;
import java.util.Optional;

@Repository
public interface PurchaseOrderRepository extends JpaRepository<PurchaseOrder, Long> {

    Optional<PurchaseOrder> findByPoNumber(String poNumber);

    boolean existsByPoNumber(String poNumber);

    Page<PurchaseOrder> findByStatus(PurchaseOrder.POStatus status, Pageable pageable);

    List<PurchaseOrder> findByProjectId(Long projectId);

    @Query("SELECT po FROM PurchaseOrder po WHERE " +
           "(:status IS NULL OR po.status = :status) AND " +
           "(:supplierId IS NULL OR po.supplierId = :supplierId) AND " +
           "(:search IS NULL OR LOWER(po.poNumber) LIKE LOWER(CONCAT('%', :search, '%')) " +
           "OR LOWER(po.supplierName) LIKE LOWER(CONCAT('%', :search, '%')))")
    Page<PurchaseOrder> findWithFilters(
            @Param("status") PurchaseOrder.POStatus status,
            @Param("supplierId") Long supplierId,
            @Param("search") String search,
            Pageable pageable);
}
