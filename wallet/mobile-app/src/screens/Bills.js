import React from 'react';
import {
  SafeAreaView,
  ScrollView,
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
} from 'react-native';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';

const BILL_CATEGORIES = [
  { key: 'electricity', label: 'Electricity', icon: 'flash-outline',  color: '#F59E0B', bg: '#FFFBEB' },
  { key: 'water',       label: 'Water',       icon: 'water-outline',  color: '#0EA5E9', bg: '#E0F2FE' },
  { key: 'tv',          label: 'DSTV / TV',   icon: 'television',     color: '#7C3AED', bg: '#F3E8FF' },
  { key: 'internet',    label: 'Internet',    icon: 'wifi',           color: '#2563EB', bg: '#EFF6FF' },
  { key: 'airtime',     label: 'Airtime',     icon: 'phone-outline',  color: '#16A34A', bg: '#F0FDF4' },
  { key: 'data',        label: 'Data Bundle', icon: 'signal',         color: '#CA8A04', bg: '#FEF9C3' },
];

export default function Bills() {
  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>Pay Bills</Text>
        <Text style={styles.subtitle}>Choose a bill category to continue</Text>
      </View>

      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.grid}>
          {BILL_CATEGORIES.map(cat => (
            <TouchableOpacity key={cat.key} style={styles.card} activeOpacity={0.85}>
              <View style={[styles.iconWrap, { backgroundColor: cat.bg }]}>
                <MaterialCommunityIcons name={cat.icon} size={22} color={cat.color} />
              </View>
              <Text style={styles.cardLabel}>{cat.label}</Text>
            </TouchableOpacity>
          ))}
        </View>

        <View style={styles.infoCard}>
          <Feather name="info" size={16} color="#6B7280" />
          <Text style={styles.infoText}>
            Bill payment integrations are coming soon. Tap a category to prepare for checkout.
          </Text>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F3F4F6' },
  header: {
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 12,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#E5E7EB',
  },
  title: { fontSize: 20, fontWeight: '800', color: '#111827' },
  subtitle: { marginTop: 4, fontSize: 12, color: '#6B7280' },
  content: { padding: 16, paddingBottom: 32, gap: 16 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  card: {
    width: '31%',
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    paddingVertical: 16,
    paddingHorizontal: 8,
    alignItems: 'center',
    gap: 8,
  },
  iconWrap: {
    width: 44,
    height: 44,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
  },
  cardLabel: {
    fontSize: 12,
    fontWeight: '700',
    color: '#111827',
    textAlign: 'center',
  },
  infoCard: {
    flexDirection: 'row',
    gap: 10,
    padding: 12,
    borderRadius: 12,
    backgroundColor: '#F9FAFB',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    alignItems: 'flex-start',
  },
  infoText: { flex: 1, fontSize: 12, color: '#374151', lineHeight: 18 },
});
