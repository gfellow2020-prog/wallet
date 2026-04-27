import React, { useCallback, useState } from 'react';
import {
  SafeAreaView,
  ScrollView,
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useAuth } from '../context/AuthContext';
import { useDialog } from '../context/DialogContext';
import WalletCard from '../components/WalletCard';

export default function Wallet({ navigation }) {
  const { user, wallet, refreshWallet } = useAuth();
  const { alert } = useDialog();
  const [refreshing, setRefreshing] = useState(false);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    try { await refreshWallet(); } catch {}
    setRefreshing(false);
  }, [refreshWallet]);

  const comingSoon = (label) =>
    alert({
      title: label,
      message: `${label} will be available soon.`,
      tone: 'info',
      icon: 'clock',
    });

  const actions = [
    {
      key: 'deposit',
      label: 'Top Up',
      subtitle: 'Add funds to your wallet',
      icon: 'plus-circle',
      tint: '#2563EB',
      bg: '#EFF6FF',
      onPress: () => navigation.navigate('Fund'),
    },
    {
      key: 'withdraw',
      label: 'Withdraw',
      subtitle: 'Cash out to bank / MoMo',
      icon: 'minus-circle',
      tint: '#DC2626',
      bg: '#FEF2F2',
      onPress: () => navigation.navigate('Withdraw'),
    },
    {
      key: 'send',
      label: 'Send Money',
      subtitle: 'Transfer to a user',
      icon: 'send',
      tint: '#059669',
      bg: '#ECFDF3',
      onPress: () => navigation.navigate('Send'),
    },
    {
      key: 'request',
      label: 'Request',
      subtitle: 'Ask someone to pay you',
      icon: 'dollar-sign',
      tint: '#D97706',
      bg: '#FEF3C7',
      onPress: () => navigation.navigate('RequestMoney'),
    },
    {
      key: 'airtime',
      label: 'Airtime',
      subtitle: 'Buy mobile airtime',
      icon: 'phone',
      tint: '#16A34A',
      bg: '#F0FDF4',
      onPress: () => comingSoon('Airtime'),
    },
    {
      key: 'data',
      label: 'Data',
      subtitle: 'Buy internet bundles',
      icon: 'wifi',
      tint: '#CA8A04',
      bg: '#FFFBEB',
      onPress: () => comingSoon('Data'),
    },
    {
      key: 'history',
      label: 'Transactions',
      subtitle: 'View ledger',
      icon: 'clock',
      tint: '#111827',
      bg: '#F3F4F6',
      onPress: () => navigation.navigate('History'),
    },
  ];

  return (
    <SafeAreaView style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Feather name="arrow-left" size={20} color="#111827" />
        </TouchableOpacity>
        <Text style={styles.title}>Wallet</Text>
        <View style={{ width: 36 }} />
      </View>

      <ScrollView
        contentContainerStyle={{ padding: 16 }}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
      >
        <WalletCard
          name={user?.name || ''}
          balance={wallet?.balance || 0}
          currency={wallet?.currency || 'ZMW'}
          cardNumber={wallet?.card_number || ''}
          expiry={wallet?.expiry || ''}
        />

        <Text style={styles.sectionTitle}>Quick Actions</Text>

        <View style={styles.grid}>
          {actions.map((a) => (
            <TouchableOpacity
              key={a.key}
              style={styles.actionCard}
              onPress={a.onPress}
              activeOpacity={0.7}
              hitSlop={{ top: 6, bottom: 6, left: 6, right: 6 }}
            >
              <View style={styles.actionTopRow}>
                <View style={[styles.actionIcon, { backgroundColor: a.bg }]}>
                  <Feather name={a.icon} size={20} color={a.tint} />
                </View>
                <Feather name="chevron-right" size={18} color="#9CA3AF" />
              </View>
              <Text style={styles.actionLabel}>{a.label}</Text>
              <Text style={styles.actionSub}>{a.subtitle}</Text>
            </TouchableOpacity>
          ))}
        </View>

        <Text style={styles.sectionTitle}>Wallet Details</Text>
        <View style={styles.detailsCard}>
          <Row label="Card Number" value={wallet?.card_number || '—'} />
          <Row label="Currency"    value={wallet?.currency || 'ZMW'} />
          <Row label="Expires"     value={wallet?.expiry || '—'} />
          <Row label="Holder"      value={user?.name || '—'} last />
        </View>

        <TouchableOpacity
          style={styles.ledgerBtn}
          onPress={() => navigation.navigate('History')}
          activeOpacity={0.85}
        >
          <Feather name="list" size={16} color="#fff" />
          <Text style={styles.ledgerBtnText}>Open Transaction Ledger</Text>
        </TouchableOpacity>
      </ScrollView>
    </SafeAreaView>
  );
}

function Row({ label, value, last }) {
  return (
    <View style={[styles.detailRow, last && { borderBottomWidth: 0 }]}>
      <Text style={styles.detailLabel}>{label}</Text>
      <Text style={styles.detailValue} numberOfLines={1}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F3F4F6' },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: '#fff',
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderBottomWidth: 1,
    borderBottomColor: '#E5E7EB',
  },
  backBtn: {
    width: 36, height: 36, borderRadius: 8,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center', alignItems: 'center',
  },
  title: { fontSize: 17, fontWeight: '800', color: '#111827' },

  sectionTitle: {
    fontSize: 13,
    fontWeight: '800',
    color: '#6B7280',
    marginTop: 8,
    marginBottom: 10,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },

  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  actionCard: {
    width: '48%',
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  actionTopRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  actionIcon: {
    width: 40, height: 40, borderRadius: 10,
    justifyContent: 'center', alignItems: 'center',
  },
  actionLabel: { fontSize: 14, fontWeight: '800', color: '#111827' },
  actionSub:   { fontSize: 11, color: '#6B7280', marginTop: 2 },

  detailsCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    paddingHorizontal: 14,
  },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
  },
  detailLabel: { color: '#6B7280', fontSize: 12, fontWeight: '600' },
  detailValue: { color: '#111827', fontSize: 13, fontWeight: '700', maxWidth: '60%' },

  ledgerBtn: {
    marginTop: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: '#111827',
    paddingVertical: 14,
    borderRadius: 10,
  },
  ledgerBtnText: { color: '#fff', fontWeight: '700', fontSize: 14 },
});
