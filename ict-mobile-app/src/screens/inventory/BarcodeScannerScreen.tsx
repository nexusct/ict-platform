/**
 * Barcode Scanner Screen
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Alert } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { CameraView, useCameraPermissions, BarcodeScanningResult } from 'expo-camera';
import { useDispatch } from 'react-redux';
import { useTheme } from '../../context/ThemeContext';
import type { AppDispatch } from '../../store';
import { fetchItemByBarcode, setSelectedItem } from '../../store/slices/inventorySlice';

export const BarcodeScannerScreen: React.FC = () => {
  const { theme } = useTheme();
  const navigation = useNavigation();
  const dispatch = useDispatch<AppDispatch>();

  const [permission, requestPermission] = useCameraPermissions();
  const [scanned, setScanned] = useState(false);

  useEffect(() => {
    if (!permission?.granted) {
      requestPermission();
    }
  }, [permission, requestPermission]);

  const handleBarCodeScanned = async (result: BarcodeScanningResult) => {
    if (scanned) return;
    setScanned(true);

    const { data } = result;

    try {
      const fetchResult = await dispatch(fetchItemByBarcode(data));
      if (fetchItemByBarcode.fulfilled.match(fetchResult)) {
        navigation.navigate('InventoryDetail', { itemId: fetchResult.payload.id } as never);
      } else {
        Alert.alert(
          'Item Not Found',
          `No item found with barcode: ${data}`,
          [
            { text: 'Cancel', style: 'cancel', onPress: () => setScanned(false) },
            { text: 'Scan Again', onPress: () => setScanned(false) },
          ]
        );
      }
    } catch (error) {
      Alert.alert('Error', 'Failed to look up barcode', [
        { text: 'OK', onPress: () => setScanned(false) },
      ]);
    }
  };

  const styles = createStyles(theme);

  if (!permission) {
    return (
      <View style={styles.container}>
        <Text style={styles.message}>Requesting camera permission...</Text>
      </View>
    );
  }

  if (!permission.granted) {
    return (
      <View style={styles.container}>
        <Text style={styles.message}>Camera permission denied</Text>
        <TouchableOpacity style={styles.button} onPress={requestPermission}>
          <Text style={styles.buttonText}>Grant Permission</Text>
        </TouchableOpacity>
        <TouchableOpacity style={[styles.button, { marginTop: 12 }]} onPress={() => navigation.goBack()}>
          <Text style={styles.buttonText}>Go Back</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <CameraView
        style={StyleSheet.absoluteFillObject}
        facing="back"
        barcodeScannerSettings={{
          barcodeTypes: [
            'qr',
            'ean13',
            'ean8',
            'upc_a',
            'upc_e',
            'code39',
            'code93',
            'code128',
            'codabar',
            'itf14',
            'pdf417',
            'aztec',
            'datamatrix',
          ],
        }}
        onBarcodeScanned={scanned ? undefined : handleBarCodeScanned}
      />

      {/* Overlay */}
      <View style={styles.overlay}>
        <View style={styles.overlayTop} />
        <View style={styles.overlayMiddle}>
          <View style={styles.overlaySide} />
          <View style={styles.scanFrame}>
            <View style={[styles.corner, styles.cornerTL]} />
            <View style={[styles.corner, styles.cornerTR]} />
            <View style={[styles.corner, styles.cornerBL]} />
            <View style={[styles.corner, styles.cornerBR]} />
          </View>
          <View style={styles.overlaySide} />
        </View>
        <View style={styles.overlayBottom}>
          <Text style={styles.instruction}>
            Position barcode within the frame
          </Text>
          <TouchableOpacity
            style={styles.cancelButton}
            onPress={() => navigation.goBack()}
          >
            <Text style={styles.cancelButtonText}>Cancel</Text>
          </TouchableOpacity>
        </View>
      </View>
    </View>
  );
};

const createStyles = (theme: any) =>
  StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: '#000',
      justifyContent: 'center',
      alignItems: 'center',
    },
    message: {
      color: '#fff',
      fontSize: 16,
      marginBottom: 20,
    },
    button: {
      backgroundColor: theme.colors.primary,
      paddingHorizontal: 24,
      paddingVertical: 12,
      borderRadius: 8,
    },
    buttonText: {
      color: '#fff',
      fontWeight: '600',
    },
    overlay: {
      ...StyleSheet.absoluteFillObject,
    },
    overlayTop: {
      flex: 1,
      backgroundColor: 'rgba(0, 0, 0, 0.6)',
    },
    overlayMiddle: {
      flexDirection: 'row',
    },
    overlaySide: {
      flex: 1,
      backgroundColor: 'rgba(0, 0, 0, 0.6)',
    },
    scanFrame: {
      width: 280,
      height: 200,
      position: 'relative',
    },
    corner: {
      position: 'absolute',
      width: 40,
      height: 40,
      borderColor: '#fff',
    },
    cornerTL: {
      top: 0,
      left: 0,
      borderTopWidth: 4,
      borderLeftWidth: 4,
    },
    cornerTR: {
      top: 0,
      right: 0,
      borderTopWidth: 4,
      borderRightWidth: 4,
    },
    cornerBL: {
      bottom: 0,
      left: 0,
      borderBottomWidth: 4,
      borderLeftWidth: 4,
    },
    cornerBR: {
      bottom: 0,
      right: 0,
      borderBottomWidth: 4,
      borderRightWidth: 4,
    },
    overlayBottom: {
      flex: 1,
      backgroundColor: 'rgba(0, 0, 0, 0.6)',
      alignItems: 'center',
      paddingTop: 40,
    },
    instruction: {
      color: '#fff',
      fontSize: 15,
      marginBottom: 24,
    },
    cancelButton: {
      backgroundColor: 'rgba(255, 255, 255, 0.2)',
      paddingHorizontal: 32,
      paddingVertical: 14,
      borderRadius: 24,
    },
    cancelButtonText: {
      color: '#fff',
      fontSize: 16,
      fontWeight: '600',
    },
  });
