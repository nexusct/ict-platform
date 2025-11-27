/**
 * Inventory Detail Screen
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

import React from 'react';
import { View, Text, ScrollView, StyleSheet, TouchableOpacity, Alert } from 'react-native';
import { useRoute } from '@react-navigation/native';
import { useDispatch, useSelector } from 'react-redux';
import { useTheme } from '../../context/ThemeContext';
import type { RootState, AppDispatch } from '../../store';
import { updateItemQuantity } from '../../store/slices/inventorySlice';

export const InventoryDetailScreen: React.FC = () => {
  const { theme } = useTheme();
  const route = useRoute();
  const dispatch = useDispatch<AppDispatch>();

  const { itemId } = route.params as { itemId: number };
  const item = useSelector((state: RootState) =>
    state.inventory.items.find((i) => i.id === itemId)
  );

  const handleAdjustQuantity = (adjustment: number) => {
    if (!item) return;

    const newQuantity = item.quantity + adjustment;
    if (newQuantity < 0) {
      Alert.alert('Error', 'Quantity cannot be negative');
      return;
    }

    Alert.alert(
      'Adjust Quantity',
      `Change quantity from ${item.quantity} to ${newQuantity}?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Confirm',
          onPress: () => {
            dispatch(
              updateItemQuantity({
                id: item.id,
                quantity: newQuantity,
                reason: adjustment > 0 ? 'Received stock' : 'Used/Removed',
              })
            );
          },
        },
      ]
    );
  };

  const styles = createStyles(theme);

  if (!item) {
    return (
      <View style={styles.container}>
        <Text style={styles.errorText}>Item not found</Text>
      </View>
    );
  }

  const isLowStock = item.quantity <= item.reorder_point;

  return (
    <ScrollView style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <View style={styles.imageContainer}>
          <Text style={styles.imagePlaceholder}>📦</Text>
        </View>
        <Text style={styles.sku}>{item.sku}</Text>
        <Text style={styles.name}>{item.name}</Text>
        <Text style={styles.category}>{item.category}</Text>
      </View>

      {/* Quantity Card */}
      <View style={styles.quantityCard}>
        <Text style={styles.quantityLabel}>Current Stock</Text>
        <View style={styles.quantityRow}>
          <TouchableOpacity
            style={styles.quantityButton}
            onPress={() => handleAdjustQuantity(-1)}
          >
            <Text style={styles.quantityButtonText}>−</Text>
          </TouchableOpacity>

          <View style={styles.quantityValue}>
            <Text style={[styles.quantityNumber, isLowStock && styles.quantityLow]}>
              {item.quantity}
            </Text>
            <Text style={styles.quantityUnit}>{item.unit}</Text>
          </View>

          <TouchableOpacity
            style={styles.quantityButton}
            onPress={() => handleAdjustQuantity(1)}
          >
            <Text style={styles.quantityButtonText}>+</Text>
          </TouchableOpacity>
        </View>

        {isLowStock && (
          <View style={styles.lowStockWarning}>
            <Text style={styles.lowStockWarningText}>
              ⚠️ Below reorder point ({item.reorder_point})
            </Text>
          </View>
        )}
      </View>

      {/* Details */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Details</Text>

        <View style={styles.detailRow}>
          <Text style={styles.detailLabel}>Unit Cost</Text>
          <Text style={styles.detailValue}>${item.unit_cost.toFixed(2)}</Text>
        </View>

        <View style={styles.detailRow}>
          <Text style={styles.detailLabel}>Total Value</Text>
          <Text style={styles.detailValue}>
            ${(item.quantity * item.unit_cost).toFixed(2)}
          </Text>
        </View>

        <View style={styles.detailRow}>
          <Text style={styles.detailLabel}>Reorder Point</Text>
          <Text style={styles.detailValue}>{item.reorder_point} {item.unit}</Text>
        </View>

        {item.location && (
          <View style={styles.detailRow}>
            <Text style={styles.detailLabel}>Location</Text>
            <Text style={styles.detailValue}>{item.location}</Text>
          </View>
        )}

        {item.barcode && (
          <View style={styles.detailRow}>
            <Text style={styles.detailLabel}>Barcode</Text>
            <Text style={styles.detailValue}>{item.barcode}</Text>
          </View>
        )}
      </View>

      {/* Description */}
      {item.description && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Description</Text>
          <Text style={styles.description}>{item.description}</Text>
        </View>
      )}

      {/* Actions */}
      <View style={styles.actions}>
        <TouchableOpacity style={styles.actionButton}>
          <Text style={styles.actionButtonText}>View History</Text>
        </TouchableOpacity>
        <TouchableOpacity style={[styles.actionButton, styles.reorderButton]}>
          <Text style={styles.reorderButtonText}>Reorder</Text>
        </TouchableOpacity>
      </View>

      <View style={{ height: 100 }} />
    </ScrollView>
  );
};

const createStyles = (theme: any) =>
  StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: theme.colors.background,
    },
    errorText: {
      textAlign: 'center',
      marginTop: 40,
      color: theme.colors.textMuted,
    },
    header: {
      alignItems: 'center',
      padding: 24,
      backgroundColor: theme.colors.card,
      borderBottomWidth: 1,
      borderBottomColor: theme.colors.border,
    },
    imageContainer: {
      width: 100,
      height: 100,
      borderRadius: 16,
      backgroundColor: theme.colors.background,
      justifyContent: 'center',
      alignItems: 'center',
      marginBottom: 16,
    },
    imagePlaceholder: {
      fontSize: 48,
    },
    sku: {
      fontSize: 13,
      color: theme.colors.textMuted,
      fontFamily: 'monospace',
      marginBottom: 4,
    },
    name: {
      fontSize: 22,
      fontWeight: '700',
      color: theme.colors.text,
      textAlign: 'center',
      marginBottom: 4,
    },
    category: {
      fontSize: 15,
      color: theme.colors.textSecondary,
    },
    quantityCard: {
      margin: 20,
      padding: 24,
      backgroundColor: theme.colors.card,
      borderRadius: 16,
      borderWidth: 1,
      borderColor: theme.colors.border,
    },
    quantityLabel: {
      fontSize: 14,
      color: theme.colors.textMuted,
      textAlign: 'center',
      marginBottom: 16,
    },
    quantityRow: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'center',
      gap: 24,
    },
    quantityButton: {
      width: 48,
      height: 48,
      borderRadius: 24,
      backgroundColor: theme.colors.primary,
      justifyContent: 'center',
      alignItems: 'center',
    },
    quantityButtonText: {
      fontSize: 28,
      fontWeight: '600',
      color: '#fff',
    },
    quantityValue: {
      alignItems: 'center',
    },
    quantityNumber: {
      fontSize: 48,
      fontWeight: '700',
      color: theme.colors.text,
    },
    quantityLow: {
      color: theme.colors.danger,
    },
    quantityUnit: {
      fontSize: 14,
      color: theme.colors.textMuted,
    },
    lowStockWarning: {
      marginTop: 16,
      padding: 12,
      backgroundColor: 'rgba(239, 68, 68, 0.1)',
      borderRadius: 8,
    },
    lowStockWarningText: {
      textAlign: 'center',
      color: theme.colors.danger,
      fontWeight: '500',
    },
    section: {
      padding: 20,
      borderTopWidth: 1,
      borderTopColor: theme.colors.border,
    },
    sectionTitle: {
      fontSize: 16,
      fontWeight: '600',
      color: theme.colors.text,
      marginBottom: 16,
    },
    detailRow: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      paddingVertical: 10,
      borderBottomWidth: 1,
      borderBottomColor: theme.colors.borderLight,
    },
    detailLabel: {
      fontSize: 14,
      color: theme.colors.textSecondary,
    },
    detailValue: {
      fontSize: 14,
      fontWeight: '500',
      color: theme.colors.text,
    },
    description: {
      fontSize: 15,
      color: theme.colors.textSecondary,
      lineHeight: 22,
    },
    actions: {
      flexDirection: 'row',
      padding: 20,
      gap: 12,
    },
    actionButton: {
      flex: 1,
      padding: 16,
      borderRadius: 12,
      backgroundColor: theme.colors.card,
      borderWidth: 1,
      borderColor: theme.colors.border,
      alignItems: 'center',
    },
    actionButtonText: {
      fontSize: 15,
      fontWeight: '600',
      color: theme.colors.text,
    },
    reorderButton: {
      backgroundColor: theme.colors.primary,
      borderColor: theme.colors.primary,
    },
    reorderButtonText: {
      fontSize: 15,
      fontWeight: '600',
      color: '#fff',
    },
  });
