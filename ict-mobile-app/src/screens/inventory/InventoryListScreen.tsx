/**
 * Inventory List Screen
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  FlatList,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  RefreshControl,
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { useDispatch, useSelector } from 'react-redux';
import { useTheme } from '../../context/ThemeContext';
import type { RootState, AppDispatch } from '../../store';
import { fetchInventory, setFilters } from '../../store/slices/inventorySlice';
import type { InventoryItem } from '../../types';

export const InventoryListScreen: React.FC = () => {
  const { theme } = useTheme();
  const navigation = useNavigation();
  const dispatch = useDispatch<AppDispatch>();

  const { items, isLoading, filters } = useSelector((state: RootState) => state.inventory);
  const [searchQuery, setSearchQuery] = useState('');

  useEffect(() => {
    dispatch(fetchInventory({}));
  }, [dispatch]);

  const onRefresh = () => {
    dispatch(fetchInventory({}));
  };

  const filteredItems = items.filter((item) => {
    const matchesSearch =
      item.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      item.sku.toLowerCase().includes(searchQuery.toLowerCase());

    const matchesLowStock = !filters.lowStock || item.quantity <= item.reorder_point;

    return matchesSearch && matchesLowStock;
  });

  const styles = createStyles(theme);

  const renderItem = ({ item }: { item: InventoryItem }) => {
    const isLowStock = item.quantity <= item.reorder_point;

    return (
      <TouchableOpacity
        style={styles.itemCard}
        onPress={() => navigation.navigate('InventoryDetail', { itemId: item.id } as never)}
      >
        <View style={styles.itemImage}>
          <Text style={styles.itemImagePlaceholder}>📦</Text>
        </View>
        <View style={styles.itemInfo}>
          <Text style={styles.itemSku}>{item.sku}</Text>
          <Text style={styles.itemName}>{item.name}</Text>
          <Text style={styles.itemCategory}>{item.category}</Text>
        </View>
        <View style={styles.itemQuantity}>
          <Text style={[styles.quantityValue, isLowStock && styles.quantityLow]}>
            {item.quantity}
          </Text>
          <Text style={styles.quantityUnit}>{item.unit}</Text>
          {isLowStock && (
            <View style={styles.lowStockBadge}>
              <Text style={styles.lowStockText}>LOW</Text>
            </View>
          )}
        </View>
      </TouchableOpacity>
    );
  };

  return (
    <View style={styles.container}>
      {/* Header Actions */}
      <View style={styles.headerActions}>
        <TouchableOpacity
          style={styles.scanButton}
          onPress={() => navigation.navigate('BarcodeScanner' as never)}
        >
          <Text style={styles.scanButtonIcon}>📷</Text>
          <Text style={styles.scanButtonText}>Scan</Text>
        </TouchableOpacity>
      </View>

      {/* Search */}
      <View style={styles.searchContainer}>
        <TextInput
          style={styles.searchInput}
          value={searchQuery}
          onChangeText={setSearchQuery}
          placeholder="Search inventory..."
          placeholderTextColor={theme.colors.textMuted}
        />
      </View>

      {/* Filters */}
      <View style={styles.filterContainer}>
        <TouchableOpacity
          style={[styles.filterChip, filters.lowStock && styles.filterChipActive]}
          onPress={() => dispatch(setFilters({ lowStock: !filters.lowStock }))}
        >
          <Text
            style={[styles.filterChipText, filters.lowStock && styles.filterChipTextActive]}
          >
            Low Stock Only
          </Text>
        </TouchableOpacity>
      </View>

      {/* List */}
      <FlatList
        data={filteredItems}
        renderItem={renderItem}
        keyExtractor={(item) => item.id.toString()}
        contentContainerStyle={styles.listContent}
        refreshControl={<RefreshControl refreshing={isLoading} onRefresh={onRefresh} />}
        ListEmptyComponent={
          <View style={styles.emptyContainer}>
            <Text style={styles.emptyText}>No items found</Text>
          </View>
        }
      />
    </View>
  );
};

const createStyles = (theme: any) =>
  StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: theme.colors.background,
    },
    headerActions: {
      flexDirection: 'row',
      justifyContent: 'flex-end',
      paddingHorizontal: 16,
      paddingTop: 12,
    },
    scanButton: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 6,
      backgroundColor: theme.colors.primary,
      paddingHorizontal: 16,
      paddingVertical: 10,
      borderRadius: 8,
    },
    scanButtonIcon: {
      fontSize: 16,
    },
    scanButtonText: {
      color: '#fff',
      fontWeight: '600',
    },
    searchContainer: {
      paddingHorizontal: 16,
      paddingVertical: 12,
    },
    searchInput: {
      backgroundColor: theme.colors.card,
      borderWidth: 1,
      borderColor: theme.colors.border,
      borderRadius: 10,
      paddingHorizontal: 16,
      paddingVertical: 12,
      fontSize: 15,
      color: theme.colors.text,
    },
    filterContainer: {
      flexDirection: 'row',
      paddingHorizontal: 16,
      paddingBottom: 12,
    },
    filterChip: {
      paddingHorizontal: 14,
      paddingVertical: 8,
      borderRadius: 20,
      backgroundColor: theme.colors.card,
      borderWidth: 1,
      borderColor: theme.colors.border,
    },
    filterChipActive: {
      backgroundColor: theme.colors.warning,
      borderColor: theme.colors.warning,
    },
    filterChipText: {
      fontSize: 13,
      color: theme.colors.textSecondary,
    },
    filterChipTextActive: {
      color: '#fff',
      fontWeight: '600',
    },
    listContent: {
      padding: 16,
      paddingTop: 0,
    },
    itemCard: {
      flexDirection: 'row',
      alignItems: 'center',
      backgroundColor: theme.colors.card,
      borderRadius: 12,
      padding: 12,
      marginBottom: 8,
      borderWidth: 1,
      borderColor: theme.colors.border,
    },
    itemImage: {
      width: 50,
      height: 50,
      borderRadius: 8,
      backgroundColor: theme.colors.background,
      justifyContent: 'center',
      alignItems: 'center',
      marginRight: 12,
    },
    itemImagePlaceholder: {
      fontSize: 24,
    },
    itemInfo: {
      flex: 1,
    },
    itemSku: {
      fontSize: 11,
      color: theme.colors.textMuted,
      fontFamily: 'monospace',
    },
    itemName: {
      fontSize: 15,
      fontWeight: '600',
      color: theme.colors.text,
      marginVertical: 2,
    },
    itemCategory: {
      fontSize: 13,
      color: theme.colors.textSecondary,
    },
    itemQuantity: {
      alignItems: 'flex-end',
    },
    quantityValue: {
      fontSize: 20,
      fontWeight: '700',
      color: theme.colors.text,
    },
    quantityLow: {
      color: theme.colors.danger,
    },
    quantityUnit: {
      fontSize: 12,
      color: theme.colors.textMuted,
    },
    lowStockBadge: {
      backgroundColor: theme.colors.danger,
      paddingHorizontal: 6,
      paddingVertical: 2,
      borderRadius: 4,
      marginTop: 4,
    },
    lowStockText: {
      fontSize: 9,
      fontWeight: '700',
      color: '#fff',
    },
    emptyContainer: {
      alignItems: 'center',
      paddingVertical: 40,
    },
    emptyText: {
      fontSize: 15,
      color: theme.colors.textMuted,
    },
  });
