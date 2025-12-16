/**
 * More Menu Screen
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

import React from 'react';
import { View, Text, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { useTheme } from '../../context/ThemeContext';
import { useAuth } from '../../context/AuthContext';
import { useOffline } from '../../context/OfflineContext';
import { useSelector } from 'react-redux';
import type { RootState } from '../../store';

export const MoreMenuScreen: React.FC = () => {
  const { theme } = useTheme();
  const { user, logout } = useAuth();
  const { isOnline, queueLength, syncNow, isSyncing } = useOffline();
  const navigation = useNavigation();
  const { unreadCount } = useSelector((state: RootState) => state.notifications);

  const menuItems = [
    { icon: '🔧', label: 'Equipment', screen: 'Equipment' },
    { icon: '🚗', label: 'Fleet', screen: 'Fleet' },
    { icon: '💰', label: 'Expenses', screen: 'Expenses' },
    { icon: '🔔', label: 'Notifications', badge: unreadCount, screen: 'Notifications' },
    { icon: '⚙️', label: 'Settings', screen: 'Settings' },
    { icon: '👤', label: 'Profile', screen: 'Profile' },
  ];

  const styles = createStyles(theme);

  return (
    <ScrollView style={styles.container}>
      {/* User Card */}
      <TouchableOpacity
        style={styles.userCard}
        onPress={() => navigation.navigate('Profile' as never)}
      >
        <View style={styles.userAvatar}>
          <Text style={styles.userAvatarText}>{user?.name?.charAt(0) || 'U'}</Text>
        </View>
        <View style={styles.userInfo}>
          <Text style={styles.userName}>{user?.name || 'User'}</Text>
          <Text style={styles.userEmail}>{user?.email || ''}</Text>
        </View>
        <Text style={styles.userArrow}>→</Text>
      </TouchableOpacity>

      {/* Sync Status */}
      <View style={styles.syncCard}>
        <View style={styles.syncStatus}>
          <View style={[styles.syncDot, isOnline ? styles.syncOnline : styles.syncOffline]} />
          <Text style={styles.syncText}>
            {isOnline ? 'Connected' : 'Offline'}
          </Text>
        </View>
        {queueLength > 0 && (
          <TouchableOpacity
            style={styles.syncButton}
            onPress={syncNow}
            disabled={isSyncing || !isOnline}
          >
            <Text style={styles.syncButtonText}>
              {isSyncing ? 'Syncing...' : `Sync ${queueLength} items`}
            </Text>
          </TouchableOpacity>
        )}
      </View>

      {/* Menu Items */}
      <View style={styles.menuSection}>
        {menuItems.map((item) => (
          <TouchableOpacity
            key={item.label}
            style={styles.menuItem}
            onPress={() => navigation.navigate(item.screen as never)}
          >
            <Text style={styles.menuIcon}>{item.icon}</Text>
            <Text style={styles.menuLabel}>{item.label}</Text>
            {item.badge ? (
              <View style={styles.menuBadge}>
                <Text style={styles.menuBadgeText}>{item.badge}</Text>
              </View>
            ) : (
              <Text style={styles.menuArrow}>›</Text>
            )}
          </TouchableOpacity>
        ))}
      </View>

      {/* Logout */}
      <TouchableOpacity style={styles.logoutButton} onPress={logout}>
        <Text style={styles.logoutText}>Sign Out</Text>
      </TouchableOpacity>

      <View style={styles.version}>
        <Text style={styles.versionText}>ICT Platform v1.0.0</Text>
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
    userCard: {
      flexDirection: 'row',
      alignItems: 'center',
      padding: 20,
      backgroundColor: theme.colors.card,
      borderBottomWidth: 1,
      borderBottomColor: theme.colors.border,
    },
    userAvatar: {
      width: 56,
      height: 56,
      borderRadius: 28,
      backgroundColor: theme.colors.primary,
      justifyContent: 'center',
      alignItems: 'center',
    },
    userAvatarText: {
      fontSize: 22,
      fontWeight: '600',
      color: '#fff',
    },
    userInfo: {
      flex: 1,
      marginLeft: 16,
    },
    userName: {
      fontSize: 18,
      fontWeight: '600',
      color: theme.colors.text,
    },
    userEmail: {
      fontSize: 14,
      color: theme.colors.textMuted,
      marginTop: 2,
    },
    userArrow: {
      fontSize: 20,
      color: theme.colors.textMuted,
    },
    syncCard: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'center',
      padding: 16,
      margin: 16,
      backgroundColor: theme.colors.card,
      borderRadius: 12,
      borderWidth: 1,
      borderColor: theme.colors.border,
    },
    syncStatus: {
      flexDirection: 'row',
      alignItems: 'center',
    },
    syncDot: {
      width: 10,
      height: 10,
      borderRadius: 5,
      marginRight: 8,
    },
    syncOnline: {
      backgroundColor: theme.colors.success,
    },
    syncOffline: {
      backgroundColor: theme.colors.danger,
    },
    syncText: {
      fontSize: 14,
      color: theme.colors.textSecondary,
    },
    syncButton: {
      backgroundColor: theme.colors.primary,
      paddingHorizontal: 14,
      paddingVertical: 8,
      borderRadius: 8,
    },
    syncButtonText: {
      color: '#fff',
      fontSize: 13,
      fontWeight: '600',
    },
    menuSection: {
      backgroundColor: theme.colors.card,
      marginHorizontal: 16,
      borderRadius: 12,
      borderWidth: 1,
      borderColor: theme.colors.border,
      overflow: 'hidden',
    },
    menuItem: {
      flexDirection: 'row',
      alignItems: 'center',
      padding: 16,
      borderBottomWidth: 1,
      borderBottomColor: theme.colors.borderLight,
    },
    menuIcon: {
      fontSize: 20,
      marginRight: 16,
    },
    menuLabel: {
      flex: 1,
      fontSize: 16,
      color: theme.colors.text,
    },
    menuArrow: {
      fontSize: 20,
      color: theme.colors.textMuted,
    },
    menuBadge: {
      backgroundColor: theme.colors.danger,
      paddingHorizontal: 8,
      paddingVertical: 2,
      borderRadius: 10,
      minWidth: 24,
      alignItems: 'center',
    },
    menuBadgeText: {
      color: '#fff',
      fontSize: 12,
      fontWeight: '600',
    },
    logoutButton: {
      margin: 16,
      padding: 16,
      backgroundColor: 'rgba(239, 68, 68, 0.1)',
      borderRadius: 12,
      alignItems: 'center',
    },
    logoutText: {
      color: theme.colors.danger,
      fontSize: 16,
      fontWeight: '600',
    },
    version: {
      alignItems: 'center',
      paddingVertical: 16,
    },
    versionText: {
      fontSize: 12,
      color: theme.colors.textMuted,
    },
  });
