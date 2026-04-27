import React, { useCallback } from 'react';
import {
  View,
  Text,
  Image,
  ScrollView,
  StyleSheet,
  ActivityIndicator,
  TouchableOpacity,
  RefreshControl,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { Feather } from '@expo/vector-icons';
import { useFocusEffect } from '@react-navigation/native';
import { useAuth } from '../context/AuthContext';
import { useCart } from '../context/CartContext';
import { useDialog } from '../context/DialogContext';

const CURRENCY = 'ZMW';
const fmt = (n) => `${CURRENCY} ${Number(n || 0).toFixed(2)}`;

export default function Cart({ navigation }) {
  const { wallet } = useAuth();
  const { items, totals, loading, mutating, refresh, updateQuantity, removeItem, clear } = useCart();
  const { confirm, alert } = useDialog();
  const insets = useSafeAreaInsets();
  const bottomPad = Math.max(insets.bottom, 12);

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, [refresh])
  );

  const balance = Number(wallet?.balance || 0);
  const gross = Number(totals.gross || 0);
  const cashback = Number(totals.cashback || 0);
  const canAfford = balance >= gross && gross > 0;

  const handleInc = async (item) => {
    const next = item.quantity + 1;
    if (next > (item.product?.stock || 0)) {
      await alert({
        title: 'Stock limit',
        message: `Only ${item.product?.stock} unit(s) available.`,
        tone: 'warn',
      });
      return;
    }
    const res = await updateQuantity(item.id, next);
    if (!res.ok) await alert({ title: 'Could not update', message: res.message, tone: 'danger' });
  };

  const handleDec = async (item) => {
    if (item.quantity <= 1) {
      handleRemove(item);
      return;
    }
    const res = await updateQuantity(item.id, item.quantity - 1);
    if (!res.ok) await alert({ title: 'Could not update', message: res.message, tone: 'danger' });
  };

  const handleRemove = async (item) => {
    const ok = await confirm({
      title: 'Remove item',
      message: `Remove "${item.product?.title}" from your cart?`,
      tone: 'danger',
      confirmLabel: 'Remove',
      cancelLabel: 'Cancel',
      confirmTone: 'danger',
    });
    if (!ok) return;
    const res = await removeItem(item.id);
    if (!res.ok) await alert({ title: 'Could not remove', message: res.message, tone: 'danger' });
  };

  const handleClear = async () => {
    if (items.length === 0) return;
    const ok = await confirm({
      title: 'Clear cart',
      message: 'Remove all items from your cart?',
      tone: 'danger',
      confirmLabel: 'Clear cart',
      cancelLabel: 'Cancel',
      confirmTone: 'danger',
    });
    if (!ok) return;
    const res = await clear();
    if (!res.ok) await alert({ title: 'Could not clear', message: res.message, tone: 'danger' });
  };

  const goCheckout = async () => {
    if (!items.length) return;
    if (!canAfford) {
      const topUp = await confirm({
        title: 'Not enough balance',
        message: `You need ${fmt(gross - balance)} more to checkout.`,
        tone: 'warn',
        confirmLabel: 'Top Up',
        cancelLabel: 'Cancel',
      });
      if (topUp) navigation.navigate('Fund');
      return;
    }
    navigation.navigate('Checkout');
  };

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity style={styles.iconBtn} onPress={() => navigation.goBack()}>
          <Feather name="chevron-left" size={22} color="#111827" />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Your Cart</Text>
        <TouchableOpacity
          style={[styles.iconBtn, items.length === 0 && { opacity: 0.3 }]}
          onPress={handleClear}
          disabled={items.length === 0}
        >
          <Feather name="trash-2" size={18} color="#DC2626" />
        </TouchableOpacity>
      </View>

      {loading && items.length === 0 ? (
        <View style={styles.centerFill}>
          <ActivityIndicator color="#111" />
          <Text style={styles.loadingText}>Loading your cart…</Text>
        </View>
      ) : items.length === 0 ? (
        <ScrollView
          contentContainerStyle={styles.emptyScroll}
          refreshControl={<RefreshControl refreshing={loading} onRefresh={refresh} />}
        >
          <View style={styles.emptyBox}>
            <View style={styles.emptyIconWrap}>
              <Feather name="shopping-bag" size={28} color="#6B7280" />
            </View>
            <Text style={styles.emptyTitle}>Your cart is empty</Text>
            <Text style={styles.emptySub}>
              Browse nearby products and tap Add to Cart to queue items for checkout.
            </Text>
            <TouchableOpacity
              style={styles.emptyCta}
              onPress={() => navigation.navigate('NearbyProducts')}
              activeOpacity={0.85}
            >
              <Feather name="map-pin" size={14} color="#fff" />
              <Text style={styles.emptyCtaText}>Shop Nearby</Text>
            </TouchableOpacity>
          </View>
        </ScrollView>
      ) : (
        <>
          <ScrollView
            style={{ flex: 1 }}
            contentContainerStyle={styles.scroll}
            refreshControl={<RefreshControl refreshing={loading} onRefresh={refresh} />}
          >
            {items.map((item) => {
              const p = item.product;
              const disabled = !item.available || item.in_stock < 1;

              return (
                <View key={item.id} style={[styles.itemCard, disabled && styles.itemCardDisabled]}>
                  <View style={styles.itemImageBox}>
                    {p?.image_url ? (
                      <Image source={{ uri: p.image_url }} style={styles.itemImage} resizeMode="cover" />
                    ) : (
                      <Feather name="package" size={24} color="#D1D5DB" />
                    )}
                  </View>

                  <View style={styles.itemBody}>
                    <Text style={styles.itemCategory} numberOfLines={1}>
                      {p?.category || 'Product'}
                    </Text>
                    <Text style={styles.itemTitle} numberOfLines={2}>
                      {p?.title || 'Unavailable product'}
                    </Text>
                    <Text style={styles.itemSeller} numberOfLines={1}>
                      {p?.seller?.name ? `by ${p.seller.name}` : 'Vendor'}
                    </Text>

                    <View style={styles.itemBottomRow}>
                      <View style={styles.qtyBox}>
                        <TouchableOpacity
                          style={styles.qtyBtn}
                          onPress={() => handleDec(item)}
                          disabled={mutating}
                          activeOpacity={0.7}
                        >
                          <Feather name="minus" size={14} color="#111827" />
                        </TouchableOpacity>
                        <Text style={styles.qtyValue}>{item.quantity}</Text>
                        <TouchableOpacity
                          style={styles.qtyBtn}
                          onPress={() => handleInc(item)}
                          disabled={mutating}
                          activeOpacity={0.7}
                        >
                          <Feather name="plus" size={14} color="#111827" />
                        </TouchableOpacity>
                      </View>
                      <Text style={styles.itemLineTotal}>{fmt(item.line_total)}</Text>
                    </View>

                    <View style={styles.itemMetaRow}>
                      <View style={styles.cashbackPill}>
                        <Feather name="gift" size={10} color="#047857" />
                        <Text style={styles.cashbackPillText}>
                          +{fmt(item.cashback_earned)} cashback
                        </Text>
                      </View>
                      <TouchableOpacity onPress={() => handleRemove(item)}>
                        <Text style={styles.removeText}>Remove</Text>
                      </TouchableOpacity>
                    </View>

                    {disabled ? (
                      <Text style={styles.unavailableText}>
                        This item is out of stock or no longer available.
                      </Text>
                    ) : null}
                  </View>
                </View>
              );
            })}

            {/* Summary */}
            <View style={styles.summaryCard}>
              <Text style={styles.summaryTitle}>Order Summary</Text>

              <View style={styles.summaryRow}>
                <Text style={styles.summaryLabel}>
                  Subtotal ({totals.item_count} item{totals.item_count === 1 ? '' : 's'})
                </Text>
                <Text style={styles.summaryValue}>{fmt(gross)}</Text>
              </View>

              <View style={styles.summaryRow}>
                <Text style={[styles.summaryLabel, { color: '#047857' }]}>Cashback earned (2%)</Text>
                <Text style={[styles.summaryValue, { color: '#047857' }]}>+ {fmt(cashback)}</Text>
              </View>

              <View style={styles.divider} />

              <View style={styles.summaryRow}>
                <Text style={styles.summaryTotal}>You pay</Text>
                <Text style={styles.summaryTotalValue}>{fmt(gross)}</Text>
              </View>
              <Text style={styles.summaryFineprint}>
                Net outflow after cashback: {fmt(gross - cashback)}
              </Text>
            </View>

            <View style={{ height: 12 }} />
          </ScrollView>

          {/* Sticky footer — respects bottom safe-area */}
          <View style={[styles.footer, { paddingBottom: bottomPad }]}>
            <View style={styles.footerLeft}>
              <Text style={styles.footerLabel}>Total</Text>
              <Text style={styles.footerTotal}>{fmt(gross)}</Text>
              <Text style={[styles.footerHint, !canAfford && { color: '#B91C1C' }]}>
                {canAfford
                  ? `Wallet: ${fmt(balance)}`
                  : `Short by ${fmt(gross - balance)}`}
              </Text>
            </View>
            <TouchableOpacity
              style={[styles.checkoutBtn, (!canAfford || mutating) && styles.checkoutBtnMuted]}
              onPress={goCheckout}
              disabled={mutating}
              activeOpacity={0.85}
            >
              <Feather
                name={canAfford ? 'arrow-right' : 'plus-circle'}
                size={16}
                color="#fff"
              />
              <Text style={styles.checkoutBtnText}>
                {canAfford ? 'Checkout' : 'Top Up'}
              </Text>
            </TouchableOpacity>
          </View>
        </>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F9FAFB' },
  centerFill: { flex: 1, justifyContent: 'center', alignItems: 'center', gap: 8 },
  loadingText: { color: '#6B7280', fontSize: 13 },

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

  emptyScroll: { flexGrow: 1, justifyContent: 'center', padding: 24 },
  emptyBox: { alignItems: 'center', gap: 10 },
  emptyIconWrap: {
    width: 64, height: 64, borderRadius: 32,
    backgroundColor: '#F3F4F6',
    alignItems: 'center', justifyContent: 'center',
    marginBottom: 4,
  },
  emptyTitle: { fontSize: 17, fontWeight: '800', color: '#111827' },
  emptySub: { fontSize: 13, color: '#6B7280', textAlign: 'center', lineHeight: 19 },
  emptyCta: {
    flexDirection: 'row', alignItems: 'center', gap: 6,
    backgroundColor: '#111827',
    borderRadius: 999,
    paddingHorizontal: 16, paddingVertical: 10,
    marginTop: 8,
  },
  emptyCtaText: { color: '#fff', fontWeight: '800', fontSize: 13 },

  itemCard: {
    flexDirection: 'row',
    gap: 12,
    backgroundColor: '#fff',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 10,
  },
  itemCardDisabled: { opacity: 0.6 },
  itemImageBox: {
    width: 86, height: 86, borderRadius: 10,
    backgroundColor: '#F3F4F6',
    alignItems: 'center', justifyContent: 'center',
    overflow: 'hidden',
  },
  itemImage: { width: '100%', height: '100%' },

  itemBody: { flex: 1, gap: 3 },
  itemCategory: {
    fontSize: 10, fontWeight: '800',
    color: '#9CA3AF', textTransform: 'uppercase', letterSpacing: 0.5,
  },
  itemTitle: { fontSize: 14, fontWeight: '800', color: '#111827', lineHeight: 18 },
  itemSeller: { fontSize: 11, color: '#6B7280' },

  itemBottomRow: {
    flexDirection: 'row', alignItems: 'center',
    justifyContent: 'space-between', marginTop: 4,
  },
  qtyBox: {
    flexDirection: 'row', alignItems: 'center',
    backgroundColor: '#F3F4F6',
    borderRadius: 999,
    overflow: 'hidden',
  },
  qtyBtn: {
    width: 28, height: 28,
    alignItems: 'center', justifyContent: 'center',
  },
  qtyValue: {
    minWidth: 22, textAlign: 'center',
    fontWeight: '800', color: '#111827', fontSize: 13,
  },
  itemLineTotal: { fontSize: 15, fontWeight: '900', color: '#111827' },

  itemMetaRow: {
    flexDirection: 'row', alignItems: 'center',
    justifyContent: 'space-between', marginTop: 6,
  },
  cashbackPill: {
    flexDirection: 'row', alignItems: 'center', gap: 4,
    backgroundColor: '#ECFDF5',
    borderWidth: 1, borderColor: '#A7F3D0',
    paddingHorizontal: 8, paddingVertical: 3,
    borderRadius: 999,
  },
  cashbackPillText: { color: '#047857', fontSize: 10, fontWeight: '800' },
  removeText: { color: '#DC2626', fontSize: 11, fontWeight: '700' },
  unavailableText: {
    marginTop: 6,
    fontSize: 11, color: '#B91C1C',
    backgroundColor: '#FEF2F2',
    padding: 6, borderRadius: 6,
  },

  summaryCard: {
    backgroundColor: '#fff',
    borderRadius: 14, borderWidth: 1, borderColor: '#E5E7EB',
    padding: 14, marginTop: 4, gap: 8,
  },
  summaryTitle: {
    fontSize: 11, fontWeight: '800',
    color: '#9CA3AF', textTransform: 'uppercase', letterSpacing: 0.5,
    marginBottom: 4,
  },
  summaryRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  summaryLabel: { fontSize: 13, color: '#374151', fontWeight: '600' },
  summaryValue: { fontSize: 13, color: '#111827', fontWeight: '700' },
  divider: { height: 1, backgroundColor: '#F3F4F6', marginVertical: 4 },
  summaryTotal: { fontSize: 14, fontWeight: '900', color: '#111827' },
  summaryTotalValue: { fontSize: 18, fontWeight: '900', color: '#111827' },
  summaryFineprint: { fontSize: 11, color: '#6B7280', marginTop: 2 },

  footer: {
    flexDirection: 'row', alignItems: 'center', gap: 12,
    paddingHorizontal: 16, paddingVertical: 12,
    backgroundColor: '#fff',
    borderTopWidth: 1, borderTopColor: '#E5E7EB',
  },
  footerLeft: { minWidth: 110 },
  footerLabel: { fontSize: 10, color: '#9CA3AF', fontWeight: '800', textTransform: 'uppercase', letterSpacing: 0.5 },
  footerTotal: { fontSize: 18, fontWeight: '900', color: '#111827', marginTop: 2 },
  footerHint: { fontSize: 11, color: '#6B7280', marginTop: 1 },
  checkoutBtn: {
    flex: 1, flexDirection: 'row',
    alignItems: 'center', justifyContent: 'center',
    gap: 8,
    backgroundColor: '#111827',
    borderRadius: 12, paddingVertical: 14,
  },
  checkoutBtnMuted: { backgroundColor: '#9CA3AF' },
  checkoutBtnText: { color: '#fff', fontWeight: '800', fontSize: 13 },
});
