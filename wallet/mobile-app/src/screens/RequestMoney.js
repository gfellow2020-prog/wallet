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
import { useAuth } from '../context/AuthContext';
import { useDialog } from '../context/DialogContext';
import KeyboardFormScreen from '../components/KeyboardFormScreen';

export default function RequestMoney({ navigation }) {
  const { wallet } = useAuth();
  const { alert } = useDialog();
  const [email, setEmail] = useState('');
  const [amount, setAmount] = useState('');
  const [note, setNote] = useState('');
  const [loading, setLoading] = useState(false);

  const submit = async () => {
    const num = parseFloat(amount);
    if (!num || num <= 0)                       return alert({ title: 'Invalid amount', message: 'Enter a valid amount.',  tone: 'warn' });
    if (!email.trim() || !email.includes('@'))  return alert({ title: 'Invalid email',  message: 'Enter a valid email.',   tone: 'warn' });

    setLoading(true);
    try {
      const res = await api.post('/wallet/request-money', {
        recipient_email: email.trim(),
        amount: num,
        note: note.trim() || undefined,
      });
      await alert({
        title: 'Request sent',
        message: res.data.message || 'Your money request has been sent.',
        tone: 'success',
        confirmLabel: 'Done',
      });
      navigation.goBack();
    } catch (err) {
      await alert({
        title: 'Request failed',
        message: err?.response?.data?.message || 'Could not send request',
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
        <Text style={styles.title}>Request Money</Text>
        <View style={{ width: 36 }} />
      </View>

      <KeyboardFormScreen contentContainerStyle={{ padding: 16 }}>
        {/* Balance Banner */}
        <View style={styles.balanceBanner}>
          <Text style={styles.balanceLabel}>Current Balance</Text>
          <Text style={styles.balanceValue}>
            {wallet?.currency || 'ZMW'} {(wallet?.balance ?? 0).toFixed(2)}
          </Text>
        </View>

        <View style={styles.card}>
          <Text style={styles.label}>Recipient Email</Text>
          <TextInput
            style={styles.input}
            placeholder="friend@example.com"
            placeholderTextColor="#9CA3AF"
            keyboardType="email-address"
            autoCapitalize="none"
            value={email}
            onChangeText={setEmail}
            returnKeyType="next"
            blurOnSubmit={false}
          />

          <Text style={styles.label}>Amount (ZMW)</Text>
          <TextInput
            style={styles.input}
            placeholder="0.00"
            placeholderTextColor="#9CA3AF"
            keyboardType="decimal-pad"
            value={amount}
            onChangeText={setAmount}
            returnKeyType="next"
            blurOnSubmit={false}
          />

          <Text style={styles.label}>Note (optional)</Text>
          <TextInput
            style={styles.input}
            placeholder="What is this for?"
            placeholderTextColor="#9CA3AF"
            value={note}
            onChangeText={setNote}
            returnKeyType="done"
            onSubmitEditing={Keyboard.dismiss}
          />

          <TouchableOpacity style={styles.button} onPress={submit} disabled={loading}>
            {loading ? <ActivityIndicator color="#fff" /> : <Text style={styles.buttonText}>Send Request</Text>}
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
  button: { marginTop: 20, backgroundColor: '#2563EB', paddingVertical: 14, borderRadius: 8, alignItems: 'center' },
  buttonText: { color: '#fff', fontWeight: '700', fontSize: 15 },
  balanceBanner: {
    backgroundColor: '#111827',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    alignItems: 'center',
  },
  balanceLabel: { color: '#9CA3AF', fontSize: 12, fontWeight: '600', textTransform: 'uppercase', letterSpacing: 0.5 },
  balanceValue: { color: '#fff', fontSize: 24, fontWeight: '800', marginTop: 4 },
});
