package com.example.inventory_service.service;

import com.example.inventory_service.repository.ProductRepository;
import com.example.inventory_service.entity.Product;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

@Service
public class InventoryBusinessService {

    @Autowired
    private ProductRepository productRepository;

    /**
     * جلب الكمية المتاحة للمنتج.
     */
    @Transactional(readOnly = true)
    public int getAvailableQuantity(Long productId) {
        Product product = productRepository.findByIdWithLock(productId)
                .orElseThrow(() -> new RuntimeException("المنتج غير موجود بـ ID: " + productId));
        return product.getAvailableQuantity();
    }

    /**
     * حجز كمية من المنتج مع حماية ضد الـ Race Condition
     */
    @Transactional
    public boolean reserveStock(Long productId, int quantity) {
        // 1. القراءة مع القفل التشاؤمي
        Product product = productRepository.findByIdWithLock(productId)
                .orElseThrow(() -> new RuntimeException("المنتج غير موجود بـ ID: " + productId));

        // 2. التحقق من توفر الكمية
        if (product.getAvailableQuantity() < quantity) {
            return false; // الكمية غير كافية
        }

        // 3. الخصم والحفظ
        product.setAvailableQuantity(product.getAvailableQuantity() - quantity);
        productRepository.save(product);
        return true;
    }

    /**
     * آلية التعويض (Rollback) لإعادة الكمية في حال فشل الطلب في لارافيل
     */
    @Transactional
    public void rollbackStock(Long productId, int quantity) {
        Product product = productRepository.findByIdWithLock(productId)
                .orElseThrow(() -> new RuntimeException("المنتج غير موجود بـ ID: " + productId));

        product.setAvailableQuantity(product.getAvailableQuantity() + quantity);
        productRepository.save(product);
    }
}