import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';

/** Display like a 16-digit card (Mastercard-style: four groups of four). */
function formatCardNumber(raw) {
  const placeholder = '••••    ••••    ••••    ••••';
  if (raw == null || String(raw).trim() === '') {
    return placeholder;
  }
  const s = String(raw).trim();
  // Preserve server mask, e.g. "**** **** **** 0002" (do not strip asterisks).
  if (/[*•]/.test(s)) {
    const groups = s.split(/\s+/).filter(Boolean);
    if (groups.length === 4) {
      return groups.map((g) => g.replace(/\*/g, '•').slice(0, 4)).join('    ');
    }
    // Normalize any other masked string into four groups of four
    const only = s.replace(/\s/g, '');
    const out = [];
    for (let i = 0; i < 16; i += 4) {
      out.push(only.slice(i, i + 4).padEnd(4, '•'));
    }
    return out.join('    ');
  }
  const digits = s.replace(/\D/g, '');
  if (digits.length === 0) {
    return placeholder;
  }
  if (digits.length >= 16) {
    const d = digits.slice(0, 16);
    return d.match(/.{1,4}/g).join('    ');
  }
  const last4 = digits.slice(-4).padStart(4, '0');
  return `••••    ••••    ••••    ${last4}`;
}

function MastercardMark() {
  return (
    <View style={styles.mcMark} accessibilityLabel="Card network mark (decorative)">
      <View style={[styles.mcCircle, styles.mcCircleLeft]} />
      <View style={[styles.mcCircle, styles.mcCircleRight]} />
    </View>
  );
}

export default function WalletCard({ name = 'Card Holder', balance = 0, currency = 'ZMW', cardNumber = '', expiry = '—/——', onPress }) {
  const Wrapper = onPress ? TouchableOpacity : View;

  return (
    <Wrapper style={styles.card} onPress={onPress} activeOpacity={onPress ? 0.9 : undefined}>
      {/* Top row */}
      <View style={styles.topRow}>
        <View>
          <Text style={styles.brand}>ExtraCash</Text>
          <Text style={styles.sub}>Digital Wallet</Text>
        </View>
        <MastercardMark />
      </View>

      {/* Card number */}
      <Text style={styles.number}>{formatCardNumber(cardNumber)}</Text>

      {/* Balance + expiry */}
      <View style={styles.midRow}>
        <View>
          <Text style={styles.label}>Available Balance</Text>
          <Text style={styles.balance}>K {Number(balance).toLocaleString('en-ZM', { minimumFractionDigits: 2 })}</Text>
        </View>
        <View style={{ alignItems: 'flex-end' }}>
          <Text style={styles.label}>Expires</Text>
          <Text style={styles.expiry}>{expiry}</Text>
        </View>
      </View>

      {/* Footer */}
      <View style={styles.footer}>
        <Text style={styles.holder}>{name.toUpperCase()}</Text>
        <Text style={styles.currency}>{currency}</Text>
      </View>
    </Wrapper>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#111',
    padding: 20,
    borderRadius: 16,
    marginBottom: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.25,
    shadowRadius: 12,
    elevation: 8,
  },
  topRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 18 },
  brand: { color: '#fff', fontWeight: '900', fontSize: 16, letterSpacing: 0.5 },
  sub: { color: 'rgba(255,255,255,0.4)', fontSize: 10, marginTop: 2, letterSpacing: 1 },
  mcMark: {
    width: 48,
    height: 30,
    position: 'relative',
    justifyContent: 'center',
  },
  mcCircle: {
    position: 'absolute',
    width: 26,
    height: 26,
    borderRadius: 13,
    top: 2,
  },
  // Yellow + orange interlock (Mastercard-inspired decorative mark)
  mcCircleLeft: {
    right: 14,
    backgroundColor: '#FBC02D',
    opacity: 0.98,
  },
  mcCircleRight: {
    right: 0,
    backgroundColor: '#F57C00',
    opacity: 0.95,
  },
  number: {
    color: 'rgba(255,255,255,0.85)',
    fontSize: 14,
    letterSpacing: 1.5,
    marginBottom: 18,
    fontVariant: ['tabular-nums'],
    fontWeight: '600',
  },
  midRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end', marginBottom: 16 },
  label: { color: 'rgba(255,255,255,0.4)', fontSize: 9, textTransform: 'uppercase', letterSpacing: 1.5, marginBottom: 4 },
  balance: { color: '#fff', fontWeight: '900', fontSize: 24 },
  expiry: { color: 'rgba(255,255,255,0.65)', fontSize: 14, fontWeight: '600' },
  footer: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.06)', paddingTop: 12 },
  holder: { color: 'rgba(255,255,255,0.45)', fontSize: 10, letterSpacing: 2 },
  currency: { color: 'rgba(255,255,255,0.35)', fontSize: 11, fontWeight: '600', letterSpacing: 1 },
});
