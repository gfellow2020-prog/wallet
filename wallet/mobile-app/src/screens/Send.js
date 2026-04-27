import React, { useState } from 'react';
import { SafeAreaView, View, Text, TextInput, TouchableOpacity, StyleSheet, ActivityIndicator, Keyboard } from 'react-native';
import { Feather } from '@expo/vector-icons';
import api from '../services/client';
import { useAuth } from '../context/AuthContext';
import { useDialog } from '../context/DialogContext';
import KeyboardFormScreen from '../components/KeyboardFormScreen';

export default function Send({ navigation }) {
  const { refreshWallet } = useAuth();
  const { confirm, alert } = useDialog();
  const [phone, setPhone] = useState('');
  const [amount, setAmount] = useState('');
  const [resolvedName, setResolvedName] = useState(null);
  const [resolving, setResolving] = useState(false);
  const [loading, setLoading] = useState(false);

  const lookupName = async () => {
    if (!phone) return;
    setResolving(true);
    try {
      const res = await api.post('/wallet/name-lookup', { phone_number: phone });
      setResolvedName(res.data.name || 'Unknown recipient');
    } catch {
      setResolvedName(null);
    } finally {
      setResolving(false);
    }
  };

  const submit = async () => {
    const num = parseFloat(amount);
    if (!phone)           return alert({ title: 'Missing info', message: 'Enter recipient phone', tone: 'warn' });
    if (!num || num <= 0) return alert({ title: 'Invalid amount', message: 'Enter a valid amount.', tone: 'warn' });

    const ok = await confirm({
      title: 'Confirm transfer',
      message: `Send money to ${resolvedName || phone}?`,
      tone: 'default',
      icon: 'send',
      confirmLabel: 'Send',
      cancelLabel: 'Cancel',
      details: [
        { label: 'Recipient', value: resolvedName || phone },
        { label: 'Amount',    value: `ZMW ${num.toFixed(2)}` },
      ],
    });
    if (!ok) return;

    setLoading(true);
    try {
      await api.post('/wallet/send', {
        phone_number: phone,
        amount: num,
        recipient: resolvedName || undefined,
      });
      await refreshWallet();
      await alert({
        title: 'Transfer successful',
        message: `ZMW ${num.toFixed(2)} sent to ${resolvedName || phone}.`,
        tone: 'success',
        confirmLabel: 'Done',
      });
      navigation.navigate('Main', { screen: 'Home' });
    } catch (err) {
      await alert({
        title: 'Transfer failed',
        message: err?.response?.data?.message || 'Transfer failed',
        tone: 'danger',
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Feather name="arrow-left" size={20} color="#111827" />
        </TouchableOpacity>
        <Text style={styles.title}>Send Money</Text>
        <View style={{ width: 36 }} />
      </View>

      <KeyboardFormScreen contentContainerStyle={styles.bodyScroll}>
        <View style={styles.card}>
          <Text style={styles.label}>Recipient Phone</Text>
          <View style={styles.phoneRow}>
            <TextInput
              style={[styles.input, { flex: 1, marginBottom: 0 }]}
              placeholder="e.g. 0977000000"
              placeholderTextColor="#9CA3AF"
              keyboardType="phone-pad"
              value={phone}
              onChangeText={t => { setPhone(t); setResolvedName(null); }}
              returnKeyType="next"
              blurOnSubmit={false}
            />
            <TouchableOpacity style={styles.lookupBtn} onPress={lookupName}>
              {resolving ? <ActivityIndicator color="#fff" size="small" /> : <Feather name="search" size={16} color="#fff" />}
            </TouchableOpacity>
          </View>
          <Text style={styles.fieldHint}>Enter recipient mobile number and tap search to confirm name.</Text>
          {resolvedName && <Text style={styles.resolvedName}>✓ {resolvedName}</Text>}

          <Text style={[styles.label, { marginTop: 16 }]}>Amount (ZMW)</Text>
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
          <Text style={styles.fieldHint}>Only positive amounts are allowed.</Text>

          <TouchableOpacity style={styles.button} onPress={submit} disabled={loading}>
            {loading ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Send Money</Text>}
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
  bodyScroll: { padding: 16, paddingBottom: 24 },
  card: { backgroundColor: '#fff', borderRadius: 12, borderWidth: 1, borderColor: '#E5E7EB', padding: 18 },
  label: { fontSize: 13, fontWeight: '600', color: '#374151', marginBottom: 8 },
  input: { borderWidth: 1, borderColor: '#E5E7EB', borderRadius: 8, paddingHorizontal: 14, paddingVertical: 12, fontSize: 16, color: '#000', backgroundColor: '#F9FAFB', marginBottom: 8 },
  fieldHint: { fontSize: 11, color: '#9CA3AF', marginBottom: 8 },
  phoneRow: { flexDirection: 'row', gap: 8, marginBottom: 4 },
  lookupBtn: { backgroundColor: '#000', borderRadius: 8, paddingHorizontal: 14, justifyContent: 'center', alignItems: 'center' },
  resolvedName: { fontSize: 13, color: '#16A34A', fontWeight: '600', marginBottom: 4 },
  button: { backgroundColor: '#000', paddingVertical: 14, borderRadius: 8, alignItems: 'center', marginTop: 8 },
  buttonText: { color: '#fff', fontWeight: '700', fontSize: 15 },
});
