import React, { useState, useEffect, useCallback } from 'react';
import {
  View, Text, StyleSheet, ScrollView, TouchableOpacity,
  TextInput, ActivityIndicator, FlatList,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { SafeAreaView } from 'react-native-safe-area-context';
import ScreenHeader from '../components/ScreenHeader';
import client from '../services/client';
import { useDialog } from '../context/DialogContext';

const CATEGORY_ICONS = {
  groceries: 'shopping-cart',
  food:      'coffee',
  fuel:      'droplet',
  retail:    'tag',
  pharmacy:  'heart',
  telecoms:  'phone',
  utilities: 'zap',
};

const CATEGORY_LABELS = {
  groceries: 'Groceries',
  food:      'Food & Drinks',
  fuel:      'Fuel',
  retail:    'Retail',
  pharmacy:  'Pharmacy',
  telecoms:  'Telecoms',
  utilities: 'Utilities',
};

export default function PayScreen({ navigation }) {
  const { show, alert } = useDialog();
  const [merchants, setMerchants]           = useState([]);
  const [categories, setCategories]         = useState([]);
  const [activeCategory, setActiveCategory] = useState(null);
  const [loading, setLoading]               = useState(true);
  const [selectedMerchant, setSelectedMerchant] = useState(null);
  const [amount, setAmount]                 = useState('');
  const [placing, setPlacing]               = useState(false);

  const getCashbackRate = (m) => {
    if (!m || !m.cashback_eligible) return 0;
    if (m.cashback_rate != null && m.cashback_rate !== '') {
      const n = parseFloat(String(m.cashback_rate), 10);
      if (!Number.isNaN(n) && n >= 0) return n;
    }
    return 0.02;
  };

  const formatRatePct = (rate) => {
    const p = rate * 100;
    return `${(p % 1 === 0 ? p : p.toFixed(1))}%`;
  };

  const fetchMerchants = useCallback(async () => {
    try {
      const { data } = await client.get('/merchants');
      const list = data.merchants;
      setMerchants(list);
      const cats = [...new Set(list.map(m => m.category))];
      setCategories(cats);
      setActiveCategory(cats[0] ?? null);
    } catch {
      await alert({ title: 'Error', message: 'Could not load partner businesses.', tone: 'danger' });
    } finally {
      setLoading(false);
    }
  }, [alert]);

  useEffect(() => { fetchMerchants(); }, [fetchMerchants]);

  const filteredMerchants = activeCategory
    ? merchants.filter(m => m.category === activeCategory)
    : merchants;

  const computePreview = (gross) => {
    if (!selectedMerchant || !gross) return null;
    const g = parseFloat(gross);
    if (isNaN(g) || g <= 0) return null;
    const fee   = parseFloat((g * 0.015).toFixed(2));
    const rate  = getCashbackRate(selectedMerchant);
    const cashback = rate > 0
      ? parseFloat(((g - fee) * rate).toFixed(2))
      : 0;
    return { gross: g, fee, cashback };
  };

  const preview = computePreview(amount);

  const handlePlaceOrder = async () => {
    const g = parseFloat(amount);
    if (!selectedMerchant) { await alert({ title: 'Select a business', message: 'Choose a partner business to pay.', tone: 'warn' }); return; }
    if (!g || g <= 0)      { await alert({ title: 'Invalid amount', message: 'Enter a valid amount.', tone: 'warn' }); return; }

    try {
      setPlacing(true);
      const { data } = await client.post('/orders', {
        merchant_id:  selectedMerchant.id,
        gross_amount: g,
      });
      const choice = await show({
        title: 'Order created',
        message: 'Your order has been created successfully.',
        tone: 'success',
        details: [
          { label: 'Reference', value: data.order.order_reference },
          { label: 'Amount', value: `ZMW ${g.toFixed(2)}` },
          { label: 'Est. cashback', value: `+ ZMW ${preview?.cashback?.toFixed(2) ?? '0.00'}`, tone: 'success' },
        ],
        actions: [
          { key: 'pay',   label: 'Pay Now', style: 'primary' },
          { key: 'later', label: 'Later',   style: 'secondary' },
        ],
      });
      if (choice === 'pay') {
        navigation.navigate('Main', { screen: 'Home' });
      } else {
        setSelectedMerchant(null);
        setAmount('');
      }
    } catch (err) {
      await alert({
        title: 'Order failed',
        message: err.response?.data?.message ?? 'Could not create order.',
        tone: 'danger',
      });
    } finally {
      setPlacing(false);
    }
  };

  if (loading) {
    return <View style={styles.center}><ActivityIndicator size="large" color="#000" /></View>;
  }

  /* ── Amount screen ── */
  if (selectedMerchant) {
    return (
      <SafeAreaView style={styles.container} edges={['top']}>
        <ScreenHeader
          title="Pay"
          subtitle="Enter amount"
          onLeftPress={() => setSelectedMerchant(null)}
        />
        <ScrollView contentContainerStyle={styles.content}>

        <View style={styles.merchantBadge}>
          <View style={styles.merchantIconBig}>
            <Feather name={CATEGORY_ICONS[selectedMerchant.category] ?? 'shopping-bag'} size={28} color="#fff" />
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.merchantBadgeName}>{selectedMerchant.name}</Text>
            <Text style={styles.merchantBadgeCat}>{CATEGORY_LABELS[selectedMerchant.category] ?? selectedMerchant.category}</Text>
          </View>
          {selectedMerchant.cashback_eligible && getCashbackRate(selectedMerchant) > 0 && (
            <View style={styles.cashbackBadge}><Text style={styles.cashbackBadgeText}>{formatRatePct(getCashbackRate(selectedMerchant))} cashback</Text></View>
          )}
        </View>

        <Text style={styles.fieldLabel}>Amount (ZMW)</Text>
        <View style={styles.amountBox}>
          <Text style={styles.amountPrefix}>ZMW</Text>
          <TextInput
            style={styles.amountInput}
            keyboardType="decimal-pad"
            placeholder="0.00"
            placeholderTextColor="#9CA3AF"
            value={amount}
            onChangeText={setAmount}
            autoFocus
          />
        </View>
        <Text style={styles.amountHint}>Enter the amount you want to pay this business.</Text>

        {preview && (
          <View style={styles.breakdownCard}>
            <Row label="Subtotal"    value={`ZMW ${preview.gross.toFixed(2)}`} />
            <Row label="Fee (1.5%)"  value={`− ZMW ${preview.fee.toFixed(2)}`} muted />
            {selectedMerchant.cashback_eligible && getCashbackRate(selectedMerchant) > 0 && (
              <Row label={`Cashback (${formatRatePct(getCashbackRate(selectedMerchant))})`} value={`+ ZMW ${preview.cashback.toFixed(2)}`} green />
            )}
            <View style={styles.divider} />
            <Row label="You Pay" value={`ZMW ${preview.gross.toFixed(2)}`} bold />
          </View>
        )}

        {!selectedMerchant.cashback_eligible && (
          <View style={styles.noCallbackNote}>
            <Feather name="info" size={14} color="#92400E" style={{ marginRight: 6 }} />
            <Text style={styles.noCashbackText}>This partner is not eligible for cashback rewards.</Text>
          </View>
        )}

        <TouchableOpacity
          style={[styles.payBtn, (!amount || placing) && { opacity: 0.5 }]}
          onPress={handlePlaceOrder}
          disabled={!amount || placing}
        >
          {placing ? <ActivityIndicator color="#fff" /> : <Text style={styles.payBtnText}>Create Order</Text>}
        </TouchableOpacity>
        </ScrollView>
      </SafeAreaView>
    );
  }

  /* ── Merchant picker ── */
  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      <ScreenHeader
        title="Pay & Earn Cashback"
        subtitle="Pick where you are shopping"
        onLeftPress={() => navigation.goBack()}
        leftIcon="arrow-left"
      />

      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.categoryRow}>
        {categories.map(cat => (
          <TouchableOpacity
            key={cat}
            style={[styles.catTab, activeCategory === cat && styles.catTabActive]}
            onPress={() => setActiveCategory(cat)}
          >
            <Feather name={CATEGORY_ICONS[cat] ?? 'grid'} size={14} color={activeCategory === cat ? '#fff' : '#374151'} />
            <Text style={[styles.catTabText, activeCategory === cat && styles.catTabTextActive]}>
              {CATEGORY_LABELS[cat] ?? cat}
            </Text>
          </TouchableOpacity>
        ))}
      </ScrollView>

      <FlatList
        data={filteredMerchants}
        keyExtractor={item => String(item.id)}
        contentContainerStyle={{ padding: 16, paddingTop: 8 }}
        renderItem={({ item }) => (
          <TouchableOpacity style={styles.merchantRow} onPress={() => setSelectedMerchant(item)} activeOpacity={0.7}>
            <View style={styles.merchantIcon}>
              <Feather name={CATEGORY_ICONS[item.category] ?? 'shopping-bag'} size={20} color="#fff" />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.merchantName}>{item.name}</Text>
              <Text style={styles.merchantCat}>{CATEGORY_LABELS[item.category] ?? item.category}</Text>
            </View>
            {item.cashback_eligible && getCashbackRate(item) > 0
              ? <View style={styles.cashbackPill}><Text style={styles.cashbackPillText}>{formatRatePct(getCashbackRate(item))} back</Text></View>
              : <Text style={styles.noCashbackLabel}>No cashback</Text>
            }
            <Feather name="chevron-right" size={18} color="#D1D5DB" style={{ marginLeft: 8 }} />
          </TouchableOpacity>
        )}
        ItemSeparatorComponent={() => <View style={{ height: 1, backgroundColor: '#F3F4F6' }} />}
        ListEmptyComponent={() => <Text style={{ color: '#9CA3AF', textAlign: 'center', marginTop: 32 }}>No partner businesses in this category.</Text>}
      />
    </SafeAreaView>
  );
}

function Row({ label, value, muted, green, bold }) {
  return (
    <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 8 }}>
      <Text style={{ color: muted ? '#9CA3AF' : '#374151', fontSize: 14 }}>{label}</Text>
      <Text style={{ fontSize: 14, fontWeight: bold ? '700' : '500', color: green ? '#10B981' : muted ? '#9CA3AF' : '#111' }}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container:         { flex: 1, backgroundColor: '#fff' },
  center:            { flex: 1, justifyContent: 'center', alignItems: 'center' },
  content:           { padding: 24, paddingBottom: 48 },
  categoryRow:       { paddingHorizontal: 16, paddingBottom: 8, gap: 8 },
  catTab:            { flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 14, paddingVertical: 8, borderRadius: 20, borderWidth: 1.5, borderColor: '#E5E7EB' },
  catTabActive:      { backgroundColor: '#000', borderColor: '#000' },
  catTabText:        { fontSize: 13, fontWeight: '600', color: '#374151' },
  catTabTextActive:  { color: '#fff' },
  merchantRow:       { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14 },
  merchantIcon:      { width: 44, height: 44, borderRadius: 12, backgroundColor: '#111', justifyContent: 'center', alignItems: 'center' },
  merchantName:      { fontSize: 15, fontWeight: '700', color: '#111' },
  merchantCat:       { fontSize: 12, color: '#9CA3AF', marginTop: 2 },
  cashbackPill:      { backgroundColor: '#D1FAE5', borderRadius: 20, paddingHorizontal: 10, paddingVertical: 4 },
  cashbackPillText:  { fontSize: 12, fontWeight: '700', color: '#065F46' },
  noCashbackLabel:   { fontSize: 11, color: '#9CA3AF' },
  merchantBadge:     { flexDirection: 'row', alignItems: 'center', gap: 14, backgroundColor: '#F9FAFB', borderRadius: 14, padding: 16, marginBottom: 28 },
  merchantIconBig:   { width: 52, height: 52, borderRadius: 14, backgroundColor: '#111', justifyContent: 'center', alignItems: 'center' },
  merchantBadgeName: { fontSize: 16, fontWeight: '800', color: '#111' },
  merchantBadgeCat:  { fontSize: 12, color: '#6B7280', marginTop: 2 },
  cashbackBadge:     { backgroundColor: '#D1FAE5', borderRadius: 20, paddingHorizontal: 10, paddingVertical: 5 },
  cashbackBadgeText: { fontSize: 12, fontWeight: '700', color: '#065F46' },
  fieldLabel:        { fontSize: 13, fontWeight: '600', color: '#374151', marginBottom: 8 },
  amountBox:         { flexDirection: 'row', alignItems: 'center', borderWidth: 2, borderColor: '#000', borderRadius: 12, paddingHorizontal: 16, paddingVertical: 12, marginBottom: 24 },
  amountPrefix:      { fontSize: 18, fontWeight: '700', color: '#9CA3AF', marginRight: 8 },
  amountInput:       { flex: 1, fontSize: 32, fontWeight: '800', color: '#111' },
  amountHint:        { fontSize: 12, color: '#9CA3AF', marginTop: -14, marginBottom: 16 },
  breakdownCard:     { backgroundColor: '#F9FAFB', borderRadius: 12, padding: 16, marginBottom: 16 },
  divider:           { height: 1, backgroundColor: '#E5E7EB', marginBottom: 10 },
  noCallbackNote:    { flexDirection: 'row', alignItems: 'center', backgroundColor: '#FFFBEB', borderRadius: 10, padding: 12, marginBottom: 20 },
  noCashbackText:    { flex: 1, color: '#92400E', fontSize: 13 },
  payBtn:            { backgroundColor: '#000', borderRadius: 14, paddingVertical: 16, alignItems: 'center', marginTop: 8 },
  payBtnText:        { color: '#fff', fontSize: 16, fontWeight: '800' },
});
