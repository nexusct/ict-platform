/**
 * Settings Screen
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

import React from 'react';
import { View, Text, ScrollView, StyleSheet, Switch, TouchableOpacity } from 'react-native';
import { useTheme } from '../../context/ThemeContext';

export const SettingsScreen: React.FC = () => {
  const { theme, themeMode, setThemeMode } = useTheme();

  const styles = createStyles(theme);

  return (
    <ScrollView style={styles.container}>
      {/* Appearance */}
      <Text style={styles.sectionTitle}>Appearance</Text>
      <View style={styles.section}>
        {(['light', 'dark', 'system'] as const).map((mode) => (
          <TouchableOpacity
            key={mode}
            style={styles.settingItem}
            onPress={() => setThemeMode(mode)}
          >
            <View>
              <Text style={styles.settingLabel}>
                {mode.charAt(0).toUpperCase() + mode.slice(1)} Mode
              </Text>
              <Text style={styles.settingDescription}>
                {mode === 'system' ? 'Follow device settings' : `Use ${mode} theme`}
              </Text>
            </View>
            <View style={[styles.radio, themeMode === mode && styles.radioSelected]}>
              {themeMode === mode && <View style={styles.radioInner} />}
            </View>
          </TouchableOpacity>
        ))}
      </View>

      {/* Notifications */}
      <Text style={styles.sectionTitle}>Notifications</Text>
      <View style={styles.section}>
        <View style={styles.settingItem}>
          <View>
            <Text style={styles.settingLabel}>Push Notifications</Text>
            <Text style={styles.settingDescription}>Receive push notifications</Text>
          </View>
          <Switch value={true} onValueChange={() => {}} />
        </View>
        <View style={styles.settingItem}>
          <View>
            <Text style={styles.settingLabel}>Time Entry Reminders</Text>
            <Text style={styles.settingDescription}>Remind to clock out</Text>
          </View>
          <Switch value={true} onValueChange={() => {}} />
        </View>
      </View>

      {/* Data & Privacy */}
      <Text style={styles.sectionTitle}>Data & Privacy</Text>
      <View style={styles.section}>
        <View style={styles.settingItem}>
          <View>
            <Text style={styles.settingLabel}>Location Tracking</Text>
            <Text style={styles.settingDescription}>Track location with time entries</Text>
          </View>
          <Switch value={true} onValueChange={() => {}} />
        </View>
        <TouchableOpacity style={styles.settingItem}>
          <View>
            <Text style={styles.settingLabel}>Clear Cache</Text>
            <Text style={styles.settingDescription}>Free up storage space</Text>
          </View>
          <Text style={styles.settingArrow}>›</Text>
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
    sectionTitle: {
      fontSize: 13,
      fontWeight: '600',
      color: theme.colors.textMuted,
      textTransform: 'uppercase',
      paddingHorizontal: 20,
      paddingTop: 24,
      paddingBottom: 8,
    },
    section: {
      backgroundColor: theme.colors.card,
      borderTopWidth: 1,
      borderBottomWidth: 1,
      borderColor: theme.colors.border,
    },
    settingItem: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'center',
      padding: 16,
      paddingHorizontal: 20,
      borderBottomWidth: 1,
      borderBottomColor: theme.colors.borderLight,
    },
    settingLabel: {
      fontSize: 16,
      color: theme.colors.text,
    },
    settingDescription: {
      fontSize: 13,
      color: theme.colors.textMuted,
      marginTop: 2,
    },
    settingArrow: {
      fontSize: 22,
      color: theme.colors.textMuted,
    },
    radio: {
      width: 22,
      height: 22,
      borderRadius: 11,
      borderWidth: 2,
      borderColor: theme.colors.border,
      justifyContent: 'center',
      alignItems: 'center',
    },
    radioSelected: {
      borderColor: theme.colors.primary,
    },
    radioInner: {
      width: 12,
      height: 12,
      borderRadius: 6,
      backgroundColor: theme.colors.primary,
    },
  });
