import React, { useState, useEffect } from 'react';
import {
  SafeAreaView,
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import QRCode from 'react-native-qrcode-svg';
import api from '../services/client';
import { useAuth } from '../context/AuthContext';
import { useDialog } from '../context/DialogContext';

export default function MyQrCode({ navigation }) {
  const { user } = useAuth();
  const { alert } = useDialog();
  const [qrPayload, setQrPayload] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadQrCode();
  }, []);

  const loadQrCode = async () => {
    try {
      const res = await api.get('/qr-code');
      setQrPayload(res.data.payload);
    } catch {
      await alert({ title: 'Error', message: 'Could not load your QR code.', tone: 'danger' });
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
        <Text style={styles.title}>My Payment QR</Text>
        <View style={{ width: 36 }} />
      </View>

      <View style={styles.content}>
        {loading ? (
          <ActivityIndicator size="large" style={{ marginVertical: 80 }} />
        ) : (
          <>
            <View style={styles.qrBox}>
              {qrPayload && (
                <QRCode
                  value={qrPayload}
                  size={240}
                  backgroundColor="#fff"
                  color="#000"
                  quietZone={10}
                />
              )}
            </View>

            <Text style={styles.nameLabel}>{user?.name}</Text>
            <Text style={styles.hint}>Show this QR code to receive payments</Text>

            <TouchableOpacity style={styles.shareBtn}>
              <Feather name="share-2" size={18} color="#fff" />
              <Text style={styles.shareText}>Share QR Code</Text>
            </TouchableOpacity>
          </>
        )}
      </View>
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
  content: { flex: 1, alignItems: 'center', paddingTop: 40, paddingBottom: 48 },
  qrBox: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 20,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.06,
    shadowRadius: 6,
    elevation: 2,
  },
  nameLabel: { marginTop: 20, fontSize: 18, fontWeight: '800', color: '#111827' },
  hint: { marginTop: 6, fontSize: 13, color: '#6B7280' },
  shareBtn: {
    marginTop: 30,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#111827',
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 10,
  },
  shareText: { color: '#fff', fontWeight: '700', fontSize: 14 },
});
