/**
 * Inventory Navigator
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { useTheme } from '../context/ThemeContext';

import { InventoryListScreen } from '../screens/inventory/InventoryListScreen';
import { InventoryDetailScreen } from '../screens/inventory/InventoryDetailScreen';
import { BarcodeScannerScreen } from '../screens/inventory/BarcodeScannerScreen';

import type { InventoryStackParamList } from '../types';

const Stack = createNativeStackNavigator<InventoryStackParamList>();

export const InventoryNavigator: React.FC = () => {
  const { theme } = useTheme();

  return (
    <Stack.Navigator
      screenOptions={{
        headerStyle: {
          backgroundColor: theme.colors.background,
        },
        headerTintColor: theme.colors.text,
        headerShadowVisible: false,
        animation: 'slide_from_right',
      }}
    >
      <Stack.Screen
        name="InventoryList"
        component={InventoryListScreen}
        options={{ title: 'Inventory' }}
      />
      <Stack.Screen
        name="InventoryDetail"
        component={InventoryDetailScreen}
        options={{ title: 'Item Details' }}
      />
      <Stack.Screen
        name="BarcodeScanner"
        component={BarcodeScannerScreen}
        options={{
          title: 'Scan Barcode',
          presentation: 'modal',
        }}
      />
    </Stack.Navigator>
  );
};
