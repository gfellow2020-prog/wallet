import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Platform } from 'react-native';
import { DrawerContentScrollView } from '@react-navigation/drawer';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '../context/AuthContext';

const ROWS = [
  { key: 'wallet', label: 'My wallet', kind: 'stack', route: 'Wallet', lib: 'feather', icon: 'credit-card' },
  { key: 'fund', label: 'Add money', kind: 'stack', route: 'Fund', lib: 'feather', icon: 'plus-circle' },
  { key: 'send', label: 'Send money', kind: 'stack', route: 'Send', lib: 'feather', icon: 'send' },
  { key: 'pay', label: 'Pay & bills', kind: 'stack', route: 'Pay', lib: 'feather', icon: 'smartphone' },
  { key: 'history', label: 'Transaction history', kind: 'stack', route: 'History', lib: 'feather', icon: 'list' },
  { key: 'messages', label: 'Messages', kind: 'stack', route: 'Messages', lib: 'feather', icon: 'message-circle' },
  { key: 'nearby', label: 'Shop nearby', kind: 'stack', route: 'NearbyProducts', lib: 'feather', icon: 'map-pin' },
  { key: 'listings', label: 'My listings', kind: 'stack', route: 'MyProducts', lib: 'feather', icon: 'package' },
  { key: 'purchased', label: 'Purchased products', kind: 'stack', route: 'PurchasedProducts', lib: 'feather', icon: 'shopping-bag' },
  { key: 'rewards', label: 'Rewards', kind: 'stack', route: 'RewardsHub', lib: 'feather', icon: 'gift' },
  { key: 'buyInbox', label: 'Buy for me inbox', kind: 'stack', route: 'BuyRequestsInbox', lib: 'feather', icon: 'inbox' },
  { key: 'kyc', label: 'Verification (KYC)', kind: 'stack', route: 'Kyc', lib: 'feather', icon: 'shield' },
];

function RowIcon({ row, color }) {
  if (row.lib === 'mci') {
    return <MaterialCommunityIcons name={row.icon} size={20} color={color} />;
  }
  return <Feather name={row.icon} size={20} color={color} />;
}

/**
 * @param {import('@react-navigation/drawer').DrawerContentComponentProps} props
 */
export default function AppDrawerContent(props) {
  const insets = useSafeAreaInsets();
  const { user } = useAuth();
  const { navigation } = props;

  const goTo = (row) => {
    if (row.kind === 'tab') {
      navigation.navigate('MainTabs', { screen: row.tab });
    } else {
      const root = navigation.getParent();
      if (root) {
        root.navigate(row.route);
      }
    }
    navigation.closeDrawer();
  };

  const openProfile = () => {
    navigation.navigate('MainTabs', { screen: 'Profile' });
    navigation.closeDrawer();
  };

  // Guarantee the brand clears the Dynamic Island / notch even when the
  // drawer renders in a context where insets.top is reported as 0.
  const safeTop = Math.max(insets.top, Platform.OS === 'ios' ? 50 : 24);
  const safeBottom = Math.max(insets.bottom, 12);

  return (
    <SafeAreaView edges={['top', 'bottom']} style={styles.root}>
      <DrawerContentScrollView
        {...props}
        contentContainerStyle={[styles.scroll, { paddingTop: 0, paddingBottom: 16 }]}
        showsVerticalScrollIndicator={false}
      >
        <View style={[styles.brandBlock, { paddingTop: safeTop + 12 }]}>
          <View style={styles.brandMark}>
            <Text style={styles.brandMarkText}>E</Text>
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.brandName}>ExtraCash</Text>
            <Text style={styles.brandSub}>Digital wallet</Text>
          </View>
        </View>

        {user ? (
          <TouchableOpacity
            style={styles.userCard}
            onPress={openProfile}
            activeOpacity={0.7}
            accessibilityRole="button"
            accessibilityLabel="Open profile"
          >
            <View style={styles.userAvatar}>
              <Text style={styles.userAvatarText}>
                {user.name
                  ? user.name
                      .split(' ')
                      .map((w) => w[0])
                      .join('')
                      .slice(0, 2)
                      .toUpperCase()
                  : 'U'}
              </Text>
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.userName} numberOfLines={1}>
                {user.name || 'Account'}
              </Text>
              <Text style={styles.userEmail} numberOfLines={1}>
                {user.email || '—'}
              </Text>
            </View>
            <Feather name="chevron-right" size={18} color="#9CA3AF" />
          </TouchableOpacity>
        ) : null}

        <View style={styles.list}>
          {ROWS.map((row) => (
            <TouchableOpacity
              key={row.key}
              style={styles.item}
              onPress={() => goTo(row)}
              activeOpacity={0.65}
            >
              <View style={styles.itemIcon}>
                <RowIcon row={row} color="#111827" />
              </View>
              <Text style={styles.itemLabel}>{row.label}</Text>
              <Feather name="chevron-right" size={18} color="#D1D5DB" />
            </TouchableOpacity>
          ))}
        </View>
      </DrawerContentScrollView>

      <View style={[styles.footer, { paddingBottom: safeBottom }]}>
        <TouchableOpacity
          style={styles.footerItem}
          onPress={openProfile}
          activeOpacity={0.7}
          accessibilityRole="button"
          accessibilityLabel="Open settings"
        >
          <View style={styles.itemIcon}>
            <Feather name="settings" size={20} color="#111827" />
          </View>
          <Text style={styles.itemLabel}>Settings</Text>
          <Feather name="chevron-right" size={18} color="#D1D5DB" />
        </TouchableOpacity>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#FAFAFA' },
  scroll: { flexGrow: 1 },
  brandBlock: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 20,
    paddingBottom: 20,
  },
  brandMark: {
    width: 38,
    height: 38,
    borderRadius: 11,
    backgroundColor: '#000',
    alignItems: 'center',
    justifyContent: 'center',
  },
  brandMarkText: {
    color: '#fff',
    fontWeight: '800',
    fontSize: 18,
    letterSpacing: -0.5,
  },
  brandName: { fontSize: 20, fontWeight: '800', color: '#111827', letterSpacing: -0.3 },
  brandSub: { fontSize: 12, color: '#9CA3AF', marginTop: 2, fontWeight: '500' },
  userCard: {
    flexDirection: 'row',
    alignItems: 'center',
    marginHorizontal: 16,
    marginBottom: 16,
    padding: 12,
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    gap: 12,
  },
  userAvatar: {
    width: 44,
    height: 44,
    borderRadius: 12,
    backgroundColor: '#000',
    alignItems: 'center',
    justifyContent: 'center',
  },
  userAvatarText: { color: '#fff', fontWeight: '800', fontSize: 15 },
  userName: { fontSize: 15, fontWeight: '700', color: '#111827' },
  userEmail: { fontSize: 12, color: '#6B7280', marginTop: 2 },
  list: { paddingHorizontal: 8, gap: 2 },
  item: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    paddingHorizontal: 12,
    borderRadius: 10,
  },
  itemIcon: { width: 32, alignItems: 'center' },
  itemLabel: { flex: 1, fontSize: 15, fontWeight: '600', color: '#374151' },
  footer: {
    borderTopWidth: 1,
    borderTopColor: '#E5E7EB',
    paddingHorizontal: 8,
    paddingTop: 8,
    backgroundColor: '#FAFAFA',
  },
  footerItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 14,
    paddingHorizontal: 12,
    borderRadius: 10,
  },
});
