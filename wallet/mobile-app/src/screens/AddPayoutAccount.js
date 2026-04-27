import React, { useState } from 'react';
import {
  SafeAreaView,
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  Keyboard,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import api from '../services/client';
import { useDialog } from '../context/DialogContext';
import KeyboardFormScreen from '../components/KeyboardFormScreen';

export default function AddPayoutAccount({ navigation }) {
  const { alert } = useDialog();
  const [type, setType] = useState('mobile_money');
  const [bankName, setBankName] = useState('');
  const [accountNumber, setAccountNumber] = useState('');
  const [accountName, setAccountName] = useState('');
  const [bankCode, setBankCode] = useState('');
  const [phoneNumber, setPhoneNumber] = useState('');
  const [resolving, setResolving] = useState(false);
  const [loading, setLoading] = useState(false);

  const lookupAccount = async () => {
    if (!bankName.trim())      return alert({ title: 'Missing info', message: 'Enter bank name',      tone: 'warn' });
    if (!accountNumber.trim()) return alert({ title: 'Missing info', message: 'Enter account number', tone: 'warn' });

    setResolving(true);
    try {
      const res = await api.post('/lenco/resolve-account', {
        account_number: accountNumber.trim(),
        bank_code: bankName.trim(),
      });
      const data = res.data?.data ?? res.data ?? {};
      const name = data.account_name || data.name;
      const code = data.bank_code || data.code;

      if (name) {
        setAccountName(name);
        if (code) setBankCode(code);
        await alert({ title: 'Account found', message: `Name: ${name}`, tone: 'success' });
      } else {
        await alert({
          title: 'Not found',
          message: 'Could not resolve account. Please enter the name manually.',
          tone: 'warn',
        });
      }
    } catch (err) {
      await alert({
        title: 'Lookup failed',
        message: err?.response?.data?.message || 'Could not resolve account. Please enter the name manually.',
        tone: 'danger',
      });
    } finally {
      setResolving(false);
    }
  };

  const submit = async () => {
    const payload = { type };

    if (type === 'mobile_money') {
      if (!phoneNumber.trim()) return alert({ title: 'Missing info', message: 'Enter mobile money number', tone: 'warn' });
      payload.phone_number = phoneNumber.trim();
    } else {
      if (!bankName.trim())      return alert({ title: 'Missing info', message: 'Enter bank name',      tone: 'warn' });
      if (!accountNumber.trim()) return alert({ title: 'Missing info', message: 'Enter account number', tone: 'warn' });
      if (!accountName.trim())   return alert({ title: 'Missing info', message: 'Enter account name',   tone: 'warn' });
      payload.bank_name = bankName.trim();
      payload.account_number = accountNumber.trim();
      payload.account_name = accountName.trim();
      if (bankCode.trim()) payload.bank_code = bankCode.trim();
    }

    setLoading(true);
    try {
      await api.post('/payout-accounts', payload);
      await alert({
        title: 'Account saved',
        message: 'Your payout account has been saved.',
        tone: 'success',
        confirmLabel: 'Done',
      });
      navigation.goBack();
    } catch (err) {
      await alert({
        title: 'Could not save',
        message: err?.response?.data?.message || 'Could not save account',
        tone: 'danger',
      });
    } finally {
      setLoading(false);
    }
  };

  const TypeButton = ({ value, label, icon }) => (
    <TouchableOpacity
      style={[styles.typeBtn, type === value && styles.typeBtnActive]}
      onPress={() => setType(value)}
      activeOpacity={0.8}
    >
      <Feather name={icon} size={18} color={type === value ? '#fff' : '#6B7280'} />
      <Text style={[styles.typeText, type === value && styles.typeTextActive]}>
        {label}
      </Text>
    </TouchableOpacity>
  );

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Feather name="arrow-left" size={20} color="#111827" />
        </TouchableOpacity>
        <Text style={styles.title}>Add Payout Account</Text>
        <View style={{ width: 36 }} />
      </View>

      <KeyboardFormScreen contentContainerStyle={{ padding: 16 }}>
        <View style={styles.card}>
          <Text style={styles.label}>Account Type</Text>
          <View style={styles.typeRow}>
            <TypeButton value="mobile_money" label="Mobile Money" icon="smartphone" />
            <TypeButton value="bank" label="Bank Account" icon="credit-card" />
          </View>

          {type === 'mobile_money' ? (
            <>
              <Text style={styles.label}>Phone Number</Text>
              <TextInput
                style={styles.input}
                placeholder="e.g. 0977 123456"
                placeholderTextColor="#9CA3AF"
                keyboardType="phone-pad"
                value={phoneNumber}
                onChangeText={setPhoneNumber}
                returnKeyType="done"
                onSubmitEditing={Keyboard.dismiss}
              />
            </>
          ) : (
            <>
              <Text style={styles.label}>Bank Name</Text>
              <TextInput
                style={styles.input}
                placeholder="e.g. Zambia National Commercial Bank"
                placeholderTextColor="#9CA3AF"
                value={bankName}
                onChangeText={setBankName}
                returnKeyType="next"
                blurOnSubmit={false}
              />

              <Text style={styles.label}>Account Number</Text>
              <View style={styles.lookupRow}>
                <TextInput
                  style={[styles.input, { flex: 1, marginBottom: 0 }]}
                  placeholder="e.g. 1234567890"
                  placeholderTextColor="#9CA3AF"
                  value={accountNumber}
                  onChangeText={setAccountNumber}
                  returnKeyType="next"
                  blurOnSubmit={false}
                />
                <TouchableOpacity
                  style={styles.lookupBtn}
                  onPress={lookupAccount}
                  disabled={resolving}
                >
                  {resolving ? (
                    <ActivityIndicator size="small" color="#fff" />
                  ) : (
                    <Text style={styles.lookupBtnText}>Lookup</Text>
                  )}
                </TouchableOpacity>
              </View>

              <Text style={styles.label}>Account Name</Text>
              <TextInput
                style={styles.input}
                placeholder="Press Lookup or type manually"
                placeholderTextColor="#9CA3AF"
                value={accountName}
                onChangeText={setAccountName}
                returnKeyType="done"
                onSubmitEditing={Keyboard.dismiss}
              />
            </>
          )}

          <TouchableOpacity style={styles.button} onPress={submit} disabled={loading}>
            {loading ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Save Account</Text>}
          </TouchableOpacity>
        </View>
      </KeyboardFormScreen>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F3F4F6' },
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', backgroundColor: '#fff', paddingHorizontal: 16, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: '#E5E7EB' },
  backBtn: { width: 36, height: 36, borderRadius: 8, backgroundColor: '#F3F4F6', justifyContent: 'center', alignItems: 'center' },
  title: { fontSize: 17, fontWeight: '800' },
  card: { backgroundColor: '#fff', borderRadius: 12, borderWidth: 1, borderColor: '#E5E7EB', padding: 18 },
  label: { fontSize: 13, fontWeight: '600', color: '#374151', marginTop: 14, marginBottom: 6 },
  input: { borderWidth: 1, borderColor: '#E5E7EB', borderRadius: 8, paddingHorizontal: 14, paddingVertical: 12, fontSize: 16, fontWeight: '600', color: '#000', backgroundColor: '#F9FAFB' },
  typeRow: { flexDirection: 'row', gap: 10, marginBottom: 4 },
  typeBtn: { flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, paddingVertical: 12, borderRadius: 8, borderWidth: 1, borderColor: '#E5E7EB', backgroundColor: '#F9FAFB' },
  typeBtnActive: { backgroundColor: '#111827', borderColor: '#111827' },
  typeText: { fontSize: 13, fontWeight: '700', color: '#6B7280' },
  typeTextActive: { color: '#fff' },
  lookupRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  lookupBtn: { backgroundColor: '#2563EB', paddingHorizontal: 16, paddingVertical: 12, borderRadius: 8, justifyContent: 'center', alignItems: 'center' },
  lookupBtnText: { color: '#fff', fontWeight: '700', fontSize: 13 },
  button: { marginTop: 20, backgroundColor: '#111827', paddingVertical: 14, borderRadius: 8, alignItems: 'center' },
  buttonText: { color: '#fff', fontWeight: '700', fontSize: 15 },
});
