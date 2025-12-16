/**
 * Project Detail Screen
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

import React, { useEffect } from 'react';
import { View, Text, ScrollView, StyleSheet, TouchableOpacity, Linking } from 'react-native';
import { useRoute, useNavigation } from '@react-navigation/native';
import { useDispatch, useSelector } from 'react-redux';
import { useTheme } from '../../context/ThemeContext';
import type { RootState, AppDispatch } from '../../store';
import { fetchProjectById } from '../../store/slices/projectsSlice';

export const ProjectDetailScreen: React.FC = () => {
  const { theme } = useTheme();
  const route = useRoute();
  const navigation = useNavigation();
  const dispatch = useDispatch<AppDispatch>();

  const { projectId } = route.params as { projectId: number };
  const { selectedProject: project, isLoading } = useSelector((state: RootState) => state.projects);

  useEffect(() => {
    dispatch(fetchProjectById(projectId));
  }, [dispatch, projectId]);

  const openMaps = () => {
    if (project?.latitude && project?.longitude) {
      const url = `https://maps.apple.com/?ll=${project.latitude},${project.longitude}`;
      Linking.openURL(url);
    } else if (project?.address) {
      const url = `https://maps.apple.com/?q=${encodeURIComponent(project.address)}`;
      Linking.openURL(url);
    }
  };

  const styles = createStyles(theme);

  if (isLoading || !project) {
    return (
      <View style={styles.loadingContainer}>
        <Text style={styles.loadingText}>Loading...</Text>
      </View>
    );
  }

  return (
    <ScrollView style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.projectNumber}>{project.project_number}</Text>
        <Text style={styles.projectName}>{project.name}</Text>
        <Text style={styles.clientName}>{project.client_name}</Text>
      </View>

      {/* Status & Priority */}
      <View style={styles.badges}>
        <View style={[styles.badge, styles.statusBadge]}>
          <Text style={styles.badgeLabel}>Status</Text>
          <Text style={styles.badgeValue}>{project.status.replace('_', ' ')}</Text>
        </View>
        <View style={[styles.badge, styles.priorityBadge]}>
          <Text style={styles.badgeLabel}>Priority</Text>
          <Text style={styles.badgeValue}>{project.priority}</Text>
        </View>
      </View>

      {/* Details */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Details</Text>

        <View style={styles.detailRow}>
          <Text style={styles.detailLabel}>Start Date</Text>
          <Text style={styles.detailValue}>
            {new Date(project.start_date).toLocaleDateString()}
          </Text>
        </View>

        <View style={styles.detailRow}>
          <Text style={styles.detailLabel}>End Date</Text>
          <Text style={styles.detailValue}>
            {new Date(project.end_date).toLocaleDateString()}
          </Text>
        </View>

        <View style={styles.detailRow}>
          <Text style={styles.detailLabel}>Budget</Text>
          <Text style={styles.detailValue}>${project.budget.toLocaleString()}</Text>
        </View>
      </View>

      {/* Address */}
      {project.address && (
        <TouchableOpacity style={styles.section} onPress={openMaps}>
          <Text style={styles.sectionTitle}>Location</Text>
          <View style={styles.addressCard}>
            <Text style={styles.addressText}>{project.address}</Text>
            <Text style={styles.addressLink}>Open in Maps →</Text>
          </View>
        </TouchableOpacity>
      )}

      {/* Description */}
      {project.description && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Description</Text>
          <Text style={styles.description}>{project.description}</Text>
        </View>
      )}

      {/* Actions */}
      <View style={styles.actions}>
        <TouchableOpacity
          style={styles.actionButton}
          onPress={() =>
            navigation.navigate('TimeTracking', { projectId: project.id } as never)
          }
        >
          <Text style={styles.actionButtonIcon}>⏱️</Text>
          <Text style={styles.actionButtonText}>Track Time</Text>
        </TouchableOpacity>

        <TouchableOpacity style={styles.actionButton}>
          <Text style={styles.actionButtonIcon}>📄</Text>
          <Text style={styles.actionButtonText}>Documents</Text>
        </TouchableOpacity>

        <TouchableOpacity style={styles.actionButton}>
          <Text style={styles.actionButtonIcon}>👥</Text>
          <Text style={styles.actionButtonText}>Team</Text>
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
    loadingContainer: {
      flex: 1,
      justifyContent: 'center',
      alignItems: 'center',
      backgroundColor: theme.colors.background,
    },
    loadingText: {
      color: theme.colors.textMuted,
    },
    header: {
      padding: 20,
      backgroundColor: theme.colors.card,
      borderBottomWidth: 1,
      borderBottomColor: theme.colors.border,
    },
    projectNumber: {
      fontSize: 13,
      color: theme.colors.textMuted,
      fontFamily: 'monospace',
      marginBottom: 4,
    },
    projectName: {
      fontSize: 24,
      fontWeight: '700',
      color: theme.colors.text,
      marginBottom: 4,
    },
    clientName: {
      fontSize: 15,
      color: theme.colors.textSecondary,
    },
    badges: {
      flexDirection: 'row',
      padding: 20,
      gap: 12,
    },
    badge: {
      flex: 1,
      padding: 16,
      borderRadius: 12,
      backgroundColor: theme.colors.card,
      borderWidth: 1,
      borderColor: theme.colors.border,
    },
    statusBadge: {},
    priorityBadge: {},
    badgeLabel: {
      fontSize: 12,
      color: theme.colors.textMuted,
      marginBottom: 4,
    },
    badgeValue: {
      fontSize: 16,
      fontWeight: '600',
      color: theme.colors.text,
      textTransform: 'capitalize',
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
    addressCard: {
      backgroundColor: theme.colors.card,
      padding: 16,
      borderRadius: 12,
      borderWidth: 1,
      borderColor: theme.colors.border,
    },
    addressText: {
      fontSize: 15,
      color: theme.colors.text,
      marginBottom: 8,
    },
    addressLink: {
      fontSize: 14,
      color: theme.colors.primary,
      fontWeight: '500',
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
      alignItems: 'center',
      padding: 16,
      backgroundColor: theme.colors.card,
      borderRadius: 12,
      borderWidth: 1,
      borderColor: theme.colors.border,
    },
    actionButtonIcon: {
      fontSize: 24,
      marginBottom: 8,
    },
    actionButtonText: {
      fontSize: 12,
      color: theme.colors.textSecondary,
      fontWeight: '500',
    },
  });
