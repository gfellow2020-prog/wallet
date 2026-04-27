import React, { useState, useCallback } from 'react';
import { useFocusEffect } from '@react-navigation/native';
import {
  SafeAreaView,
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  RefreshControl,
  Keyboard,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import api from '../services/client';
import { useAuth } from '../context/AuthContext';
import { useDialog } from '../context/DialogContext';
import KeyboardFormScreen from '../components/KeyboardFormScreen';

export default function Withdraw({ navigation }) {
  const { refreshWallet, wallet } = useAuth();
  const { confirm, alert } = useDialog();
  const [amount, setAmount] = useState('');
  const [accounts, setAccounts] = useState([]);
  const [selectedId, setSelectedId] = useState(null);
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [fetching, setFetching] = useState(true);

  const loadAccounts = useCallback(async () => {
    try {
      const res = await api.get('/payout-accounts');
      const list = res.data?.accounts ?? [];
      setAccounts(list);
      // Auto-select default
      const def = list.find((a) => a.is_default);
      if (def) setSelectedId(def.id);
      else if (list.length > 0) setSelectedId(list[0].id);
    } catch (err) {
      await alert({
        title: 'Error',
        message: err?.response?.data?.message || 'Could not load accounts',
        tone: 'danger',
      });
    }
  }, [alert]);

  useFocusEffect(
    useCallback(() => {
      setFetching(true);
      loadAccounts().finally(() => setFetching(false));
    }, [loadAccounts])
  );

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await loadAccounts();
    setRefreshing(false);
  }, [loadAccounts]);

  const submit = async () => {
    const num = parseFloat(amount);
    if (!num || num < 5) {
      return alert({ title: 'Minimum amount', message: 'Minimum withdrawal is ZMW 5.00', tone: 'warn' });
    }
    if (!selectedId) {
      return alert({ title: 'No account', message: 'Select a payout account or add one first.', tone: 'warn' });
    }

    setLoading(true);
    try {
      const res = await api.post('/wallet/withdraw', {
        amount: num,
        payout_account_id: selectedId,
        narration: 'Wallet withdrawal',
      });
      await refreshWallet();
      await alert({
        title: 'Withdrawal initiated',
        message: res.data.message || 'Your withdrawal request has been submitted.',
        tone: 'success',
        confirmLabel: 'Done',
      });
      navigation.goBack();
    } catch (err) {
      if (err?.response?.status === 403 && err?.response?.data?.kyc_status) {
        await alert({
          title: 'KYC required',
          message: err?.response?.data?.message || 'Verify your identity before withdrawing.',
          tone: 'warn',
        });
        navigation.navigate('Kyc');
        return;
      }

      await alert({
        title: 'Withdrawal failed',
        message: err?.response?.data?.message || 'Could not process withdrawal',
        tone: 'danger',
      });
    } finally {
      setLoading(false);
    }
  };

  const deleteAccount = async (account) => {
    const ok = await confirm({
      title: 'Remove account?',
      message: `${account.bank_name || account.phone_number}\nThis cannot be undone.`,
      tone: 'danger',
      confirmLabel: 'Remove',
      cancelLabel: 'Cancel',
      confirmTone: 'danger',
    });
    if (!ok) return;
    try {
      await api.delete(`/payout-accounts/${account.id}`);
      setAccounts((prev) => prev.filter((a) => a.id !== account.id));
      if (selectedId === account.id) setSelectedId(null);
    } catch {
      await alert({
        title: 'Error',
        message: 'Could not remove account',
        tone: 'danger',
      });
    }
  };

  const renderAccount = (account) => {
    const isSelected = selectedId === account.id;
    const isBank = account.type === 'bank';

    // Detect Zambian mobile network from number prefix
    let network = '';
    if (!isBank && account.phone_number) {
      const num = account.phone_number.replace(/\D/g, '').slice(0, 3);
      if (num.startsWith('097')) network = 'Airtel';
      else if (num.startsWith('096')) network = 'MTN';
      else if (num.startsWith('095')) network = 'Zamtel';
    }

    return (
      <TouchableOpacity
        key={account.id}
        style={[styles.accountCard, isSelected && styles.accountCardActive]}
        onPress={() => setSelectedId(account.id)}
        activeOpacity={0.8}
      >
        <View style={styles.accountRow}>
          <View style={[styles.accountIcon, { backgroundColor: isBank ? '#EFF6FF' : '#F0FDF4' }]}>
            <Feather
              name={isBank ? 'credit-card' : 'smartphone'}
              size={18}
              color={isBank ? '#2563EB' : '#16A34A'}
            />
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.accountTitle}>
              {isBank ? account.bank_name : network || 'Mobile Money'}
              {account.is_default && (
                <Text style={styles.defaultBadge}>  DEFAULT</Text>
              )}
            </Text>
            <Text style={styles.accountSub}>
              {isBank
                ? `${account.account_number} • ${account.account_name}`
                : account.phone_number}
            </Text>
          </View>

          <TouchableOpacity
            style={styles.removeBtn}
            onPress={(e) => {
              e.stopPropagation();
              deleteAccount(account);
            }}
          >
            <Feather name="trash-2" size={16} color="#DC2626" />
          </TouchableOpacity>

          <View style={styles.radio}>
            {isSelected && <View style={styles.radioInner} />}
          </View>
        </View>
      </TouchableOpacity>
    );
  };

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Feather name="arrow-left" size={20} color="#111827" />
        </TouchableOpacity>
        <Text style={styles.title}>Withdraw</Text>
        <View style={{ width: 36 }} />
      </View>

      <KeyboardFormScreen
        contentContainerStyle={{ padding: 16 }}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
      >
        <View style={styles.balanceBanner}>
          <Text style={styles.balanceLabel}>Available Balance</Text>
          <Text style={styles.balanceValue}>
            {wallet?.currency || 'ZMW'} {(wallet?.balance ?? 0).toFixed(2)}
          </Text>
        </View>

        <View style={styles.card}>
          <Text style={styles.label}>Amount (ZMW)</Text>
          <TextInput
            style={styles.input}
            placeholder="0.00"
            placeholderTextColor="#9CA3AF"
            keyboardType="decimal-pad"
            value={amount}
            onChangeText={setAmount}
            returnKeyType="done"
            onSubmitEditing={Keyboard.dismiss}
          />

          <View style={styles.sectionHeader}>
            <Text style={styles.label}>Payout Account</Text>
            <TouchableOpacity onPress={() => navigation.navigate('AddPayoutAccount')}>
              <Text style={styles.addLink}>+ Add New</Text>
            </TouchableOpacity>
          </View>
          <Text style={styles.pullHint}>👇 Pull down to refresh if your account is not showing</Text>

          {fetching ? (
            <ActivityIndicator style={{ marginVertical: 20 }} />
          ) : accounts.length === 0 ? (
            <View style={styles.emptyState}>
              <Feather name="credit-card" size={32} color="#9CA3AF" />
              <Text style={styles.emptyText}>No payout accounts saved</Text>
              <TouchableOpacity
                style={styles.emptyBtn}
                onPress={() => navigation.navigate('AddPayoutAccount')}
              >
                <Text style={styles.emptyBtnText}>Add Payout Account</Text>
              </TouchableOpacity>
            </View>
          ) : (
            <View style={{ gap: 10 }}>
              {accounts.map(renderAccount)}
            </View>
          )}

          <TouchableOpacity
            style={[styles.button, (!selectedId || !amount) && styles.buttonDisabled]}
            onPress={submit}
            disabled={loading || !selectedId || !amount}
          >
            {loading ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.buttonText}>Withdraw Funds</Text>
            )}
          </TouchableOpacity>
        </View>
      </KeyboardFormScreen>
    </SafeAreaView>
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
  backBtn: { width: 36, height: 36, borderRadius: 8, backgroundColor: '#F3F4F6', justifyContent: 'center', alignItems: 'center' },
  title: { fontSize: 17, fontWeight: '800' },
  balanceBanner: { backgroundColor: '#111827', borderRadius: 12, padding: 16, marginBottom: 12, alignItems: 'center' },
  balanceLabel: { color: '#9CA3AF', fontSize: 12, fontWeight: '600', textTransform: 'uppercase', letterSpacing: 0.5 },
  balanceValue: { color: '#fff', fontSize: 24, fontWeight: '800', marginTop: 4 },
  card: { backgroundColor: '#fff', borderRadius: 12, borderWidth: 1, borderColor: '#E5E7EB', padding: 18 },
  label: { fontSize: 13, fontWeight: '600', color: '#374151', marginTop: 14, marginBottom: 6 },
  input: { borderWidth: 1, borderColor: '#E5E7EB', borderRadius: 8, paddingHorizontal: 14, paddingVertical: 12, fontSize: 16, fontWeight: '600', color: '#000', backgroundColor: '#F9FAFB' },
  sectionHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 14, marginBottom: 6 },
  addLink: { fontSize: 13, fontWeight: '700', color: '#2563EB' },
  pullHint: { fontSize: 11, color: '#9CA3AF', marginTop: -4, marginBottom: 8 },
  accountCard: {
    borderWidth: 1,
    borderColor: '#E5E7EB',
    borderRadius: 10,
    padding: 12,
    backgroundColor: '#F9FAFB',
  },
  accountCardActive: {
    borderColor: '#111827',
    backgroundColor: '#F3F4F6',
  },
  accountRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  accountIcon: { width: 36, height: 36, borderRadius: 8, justifyContent: 'center', alignItems: 'center' },
  accountTitle: { fontSize: 14, fontWeight: '700', color: '#111827' },
  defaultBadge: { fontSize: 10, fontWeight: '800', color: '#16A34A' },
  accountSub: { fontSize: 12, color: '#6B7280', marginTop: 2 },
  radio: {
    width: 20,
    height: 20,
    borderRadius: 10,
    borderWidth: 2,
    borderColor: '#9CA3AF',
    justifyContent: 'center',
    alignItems: 'center',
    marginLeft: 8,
  },
  radioInner: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: '#111827',
  },
  removeBtn: {
    width: 30,
    height: 30,
    justifyContent: 'center',
    alignItems: 'center',
  },
  emptyState: { alignItems: 'center', paddingVertical: 30, gap: 8 },
  emptyText: { fontSize: 14, fontWeight: '600', color: '#6B7280' },
  emptyBtn: { marginTop: 8, backgroundColor: '#111827', paddingHorizontal: 20, paddingVertical: 10, borderRadius: 8 },
  emptyBtnText: { color: '#fff', fontWeight: '700', fontSize: 13 },
  button: { marginTop: 20, backgroundColor: '#DC2626', paddingVertical: 14, borderRadius: 8, alignItems: 'center' },
  buttonDisabled: { backgroundColor: '#FCA5A5' },
  buttonText: { color: '#fff', fontWeight: '700', fontSize: 15 },
});
