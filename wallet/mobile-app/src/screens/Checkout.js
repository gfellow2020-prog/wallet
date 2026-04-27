import React, { useState } from 'react';
import {
  View,
  Text,
  Image,
  ScrollView,
  StyleSheet,
  ActivityIndicator,
  TouchableOpacity,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { Feather } from '@expo/vector-icons';
import { useAuth } from '../context/AuthContext';
import { useCart } from '../context/CartContext';
import { useDialog } from '../context/DialogContext';

const CURRENCY = 'ZMW';
const fmt = (n) => `${CURRENCY} ${Number(n || 0).toFixed(2)}`;

export default function Checkout({ navigation }) {
  const { wallet, refreshWallet } = useAuth();
  const { items, totals, checkout, mutating } = useCart();
  const { confirm, alert, show } = useDialog();
  const insets = useSafeAreaInsets();
  const bottomPad = Math.max(insets.bottom, 12);

  const [placing, setPlacing] = useState(false);

  const balance = Number(wallet?.balance || 0);
  const gross = Number(totals.gross || 0);
  const cashback = Number(totals.cashback || 0);
  const netOutflow = gross - cashback;
  const walletAfter = balance - gross + cashback;
  const canAfford = balance >= gross && gross > 0;

  const presentSuccess = async (order) => {
    const key = await show({
      title: 'Order placed!',
      message: 'Your cashback has been credited to your wallet.',
      tone: 'success',
      dismissible: false,
      details: [
        { label: 'Order ref', value: order?.reference || '—' },
        { label: 'Total paid', value: fmt(order?.total_paid) },
        { label: 'Cashback earned', value: `+ ${fmt(order?.cashback_earned)}`, tone: 'success' },
        { label: 'Net outflow', value: fmt(order?.net_paid) },
      ],
      actions: [
        { key: 'home',    label: 'Back to Home',      style: 'primary' },
        { key: 'history', label: 'View transactions', style: 'secondary' },
      ],
    });
    if (key === 'history') navigation.navigate('History');
    else navigation.navigate('Main');
  };

  const handlePlaceOrder = async () => {
    if (!canAfford) {
      const topUp = await confirm({
        title: 'Not enough balance',
        message: `You need ${fmt(gross - balance)} more to complete this order.`,
        tone: 'warn',
        confirmLabel: 'Top Up',
        cancelLabel: 'Cancel',
      });
      if (topUp) navigation.navigate('Fund');
      return;
    }

    const ok = await confirm({
      title: 'Confirm order',
      message: 'Review your order before placing it.',
      tone: 'default',
      icon: 'shopping-bag',
      confirmLabel: 'Place Order',
      cancelLabel: 'Cancel',
      details: [
        { label: 'Order total', value: fmt(gross) },
        { label: 'Cashback (2%)', value: `+ ${fmt(cashback)}`, tone: 'success' },
        { label: 'Wallet after', value: fmt(walletAfter) },
      ],
    });
    if (!ok) return;

    setPlacing(true);
    const res = await checkout();
    await refreshWallet();
    setPlacing(false);
    if (!res.ok) {
      await alert({
        title: 'Checkout failed',
        message: res.message || 'Please try again.',
        tone: 'danger',
      });
      return;
    }
    await presentSuccess(res.order);
  };

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity style={styles.iconBtn} onPress={() => navigation.goBack()}>
          <Feather name="chevron-left" size={22} color="#111827" />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Checkout</Text>
        <View style={styles.iconBtn} />
      </View>

      {items.length === 0 ? (
        <View style={styles.centerFill}>
          <Feather name="shopping-bag" size={28} color="#9CA3AF" />
          <Text style={styles.emptyTitle}>Nothing to checkout</Text>
          <Text style={styles.emptySub}>Your cart is empty.</Text>
          <TouchableOpacity
            style={styles.emptyCta}
            onPress={() => navigation.navigate('NearbyProducts')}
          >
            <Text style={styles.emptyCtaText}>Shop Nearby</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <>
          <ScrollView style={{ flex: 1 }} contentContainerStyle={styles.scroll}>
            {/* Wallet math card */}
            <View style={styles.mathCard}>
              <View style={styles.mathHeaderRow}>
                <View style={styles.mathHeaderLeft}>
                  <Feather name="credit-card" size={14} color="#6B7280" />
                  <Text style={styles.mathHeaderLabel}>Wallet math</Text>
                </View>
                <View style={[styles.statusPill, canAfford ? styles.statusOk : styles.statusWarn]}>
                  <Feather
                    name={canAfford ? 'check-circle' : 'alert-circle'}
                    size={11}
                    color={canAfford ? '#047857' : '#B45309'}
                  />
                  <Text style={[styles.statusText, canAfford ? { color: '#047857' } : { color: '#B45309' }]}>
                    {canAfford ? 'Ready to pay' : `${fmt(gross - balance)} short`}
                  </Text>
                </View>
              </View>

              <Text style={styles.balanceBig}>{fmt(balance)}</Text>
              <Text style={styles.balanceSub}>Available balance</Text>

              <View style={styles.row}>
                <Text style={styles.rowLabel}>Order total</Text>
                <Text style={styles.rowValue}>- {fmt(gross)}</Text>
              </View>
              <View style={styles.row}>
                <Text style={[styles.rowLabel, { color: '#047857' }]}>Cashback (2%)</Text>
                <Text style={[styles.rowValue, { color: '#047857' }]}>+ {fmt(cashback)}</Text>
              </View>
              <View style={styles.divider} />
              <View style={styles.row}>
                <Text style={styles.rowTotalLabel}>Wallet after order</Text>
                <Text
                  style={[
                    styles.rowTotalValue,
                    canAfford ? { color: '#111827' } : { color: '#9CA3AF' },
                  ]}
                >
                  {fmt(walletAfter)}
                </Text>
              </View>

              <View style={styles.earnCallout}>
                <Feather name="trending-up" size={14} color="#fff" />
                <Text style={styles.earnCalloutText}>
                  Net outflow:{' '}
                  <Text style={{ fontWeight: '900' }}>{fmt(netOutflow)}</Text> · You keep{' '}
                  <Text style={{ fontWeight: '900' }}>{fmt(cashback)}</Text> as cashback
                </Text>
              </View>
            </View>

            {/* Line items */}
            <Text style={styles.sectionTitle}>
              {totals.line_count} item{totals.line_count === 1 ? '' : 's'} · {totals.item_count} unit{totals.item_count === 1 ? '' : 's'}
            </Text>

            {items.map((item) => {
              const p = item.product;
              return (
                <View key={item.id} style={styles.lineRow}>
                  <View style={styles.lineImgBox}>
                    {p?.image_url ? (
                      <Image source={{ uri: p.image_url }} style={styles.lineImg} resizeMode="cover" />
                    ) : (
                      <Feather name="package" size={18} color="#D1D5DB" />
                    )}
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.lineTitle} numberOfLines={1}>{p?.title || 'Product'}</Text>
                    <Text style={styles.lineMeta}>
                      {fmt(item.unit_price)} × {item.quantity}
                      {p?.seller?.name ? ` · ${p.seller.name}` : ''}
                    </Text>
                  </View>
                  <Text style={styles.lineTotal}>{fmt(item.line_total)}</Text>
                </View>
              );
            })}

            {/* Payment method — wallet */}
            <Text style={styles.sectionTitle}>Payment method</Text>
            <View style={styles.payMethodCard}>
              <View style={styles.payIcon}>
                <Feather name="credit-card" size={18} color="#111827" />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={styles.payTitle}>ExtraCash Wallet</Text>
                <Text style={styles.paySub}>
                  {wallet?.card_number || 'Your ExtraCash wallet'} · Balance {fmt(balance)}
                </Text>
              </View>
              <Feather name="check-circle" size={18} color="#10B981" />
            </View>

            <View style={{ height: 12 }} />
          </ScrollView>

          {/* Footer */}
          <View style={[styles.footer, { paddingBottom: bottomPad }]}>
            <View style={styles.footerLeft}>
              <Text style={styles.footerLabel}>You pay</Text>
              <Text style={styles.footerTotal}>{fmt(gross)}</Text>
              <Text style={styles.footerHint}>
                Earn {fmt(cashback)} cashback
              </Text>
            </View>
            <TouchableOpacity
              style={[styles.placeBtn, (!canAfford || placing || mutating) && styles.placeBtnMuted]}
              onPress={handlePlaceOrder}
              disabled={placing || mutating}
              activeOpacity={0.85}
            >
              {placing || mutating ? (
                <ActivityIndicator color="#fff" />
              ) : (
                <>
                  <Feather name={canAfford ? 'check' : 'plus-circle'} size={16} color="#fff" />
                  <Text style={styles.placeBtnText}>
                    {canAfford ? 'Place Order' : 'Top Up to Continue'}
                  </Text>
                </>
              )}
            </TouchableOpacity>
          </View>
        </>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F9FAFB' },
  centerFill: { flex: 1, justifyContent: 'center', alignItems: 'center', gap: 8, padding: 24 },
  emptyTitle: { fontSize: 16, fontWeight: '800', color: '#111827' },
  emptySub: { fontSize: 13, color: '#6B7280' },
  emptyCta: {
    marginTop: 10,
    backgroundColor: '#111827',
    borderRadius: 999,
    paddingHorizontal: 16,
    paddingVertical: 10,
  },
  emptyCtaText: { color: '#fff', fontWeight: '800', fontSize: 13 },

  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 14,
    paddingVertical: 10,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#E5E7EB',
  },
  iconBtn: {
    width: 36, height: 36, borderRadius: 18,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center', alignItems: 'center',
  },
  headerTitle: { fontSize: 15, fontWeight: '800', color: '#111827' },

  scroll: { padding: 16, gap: 12 },

  mathCard: {
    backgroundColor: '#fff',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 16,
  },
  mathHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  mathHeaderLeft: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  mathHeaderLabel: { fontSize: 11, fontWeight: '800', color: '#6B7280', textTransform: 'uppercase', letterSpacing: 0.5 },
  statusPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 999,
    borderWidth: 1,
  },
  statusOk: { backgroundColor: '#ECFDF5', borderColor: '#A7F3D0' },
  statusWarn: { backgroundColor: '#FFFBEB', borderColor: '#FCD34D' },
  statusText: { fontSize: 11, fontWeight: '800' },

  balanceBig: { fontSize: 26, fontWeight: '900', color: '#111827', marginTop: 8 },
  balanceSub: { fontSize: 11, color: '#9CA3AF', fontWeight: '600', marginBottom: 12 },

  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 6 },
  rowLabel: { fontSize: 13, color: '#374151', fontWeight: '600' },
  rowValue: { fontSize: 13, fontWeight: '700', color: '#111827' },
  divider: { height: 1, backgroundColor: '#F3F4F6', marginVertical: 10 },
  rowTotalLabel: { fontSize: 13, fontWeight: '800', color: '#111827' },
  rowTotalValue: { fontSize: 16, fontWeight: '900' },

  earnCallout: {
    marginTop: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#111827',
    borderRadius: 12,
    paddingVertical: 10,
    paddingHorizontal: 12,
  },
  earnCalloutText: { flex: 1, color: '#fff', fontSize: 12, lineHeight: 18 },

  sectionTitle: {
    fontSize: 11,
    fontWeight: '800',
    color: '#9CA3AF',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    marginTop: 6,
  },

  lineRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 10,
  },
  lineImgBox: {
    width: 44, height: 44, borderRadius: 8,
    backgroundColor: '#F3F4F6',
    alignItems: 'center', justifyContent: 'center',
    overflow: 'hidden',
  },
  lineImg: { width: '100%', height: '100%' },
  lineTitle: { fontSize: 13, fontWeight: '700', color: '#111827' },
  lineMeta: { fontSize: 11, color: '#6B7280', marginTop: 2 },
  lineTotal: { fontSize: 13, fontWeight: '800', color: '#111827' },

  payMethodCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 12,
  },
  payIcon: {
    width: 38, height: 38, borderRadius: 10,
    backgroundColor: '#F3F4F6',
    alignItems: 'center', justifyContent: 'center',
  },
  payTitle: { fontSize: 13, fontWeight: '800', color: '#111827' },
  paySub: { fontSize: 11, color: '#6B7280', marginTop: 2 },

  footer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: '#fff',
    borderTopWidth: 1,
    borderTopColor: '#E5E7EB',
  },
  footerLeft: { minWidth: 110 },
  footerLabel: { fontSize: 10, color: '#9CA3AF', fontWeight: '800', textTransform: 'uppercase', letterSpacing: 0.5 },
  footerTotal: { fontSize: 18, fontWeight: '900', color: '#111827', marginTop: 2 },
  footerHint: { fontSize: 11, color: '#047857', fontWeight: '700', marginTop: 1 },
  placeBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: '#111827',
    borderRadius: 12,
    paddingVertical: 14,
  },
  placeBtnMuted: { backgroundColor: '#9CA3AF' },
  placeBtnText: { color: '#fff', fontWeight: '800', fontSize: 13 },
});
