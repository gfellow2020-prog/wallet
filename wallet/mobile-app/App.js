import 'react-native-gesture-handler';
import 'react-native-reanimated';
import React, { useEffect, useState } from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createDrawerNavigator } from '@react-navigation/drawer';
import { View, Text, TouchableOpacity, StyleSheet, ActivityIndicator, Platform } from 'react-native';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { SafeAreaProvider, useSafeAreaInsets } from 'react-native-safe-area-context';
import * as Notifications from 'expo-notifications';
import { StatusBar } from 'expo-status-bar';

import AppDrawerContent from './src/components/AppDrawerContent';
import { validateApiConfig } from './src/config/env';

import { AuthProvider, useAuth } from './src/context/AuthContext';
import { CartProvider } from './src/context/CartContext';
import { DialogProvider } from './src/context/DialogContext';

import LoginScreen    from './src/screens/Login';
import RegisterScreen from './src/screens/Register';
import OtpVerifyScreen from './src/screens/OtpVerify';
import ForgotPasswordScreen from './src/screens/ForgotPassword';
import ResetPasswordScreen from './src/screens/ResetPassword';
import HomeScreen     from './src/screens/Home';
import FundScreen     from './src/screens/Fund';
import SendScreen     from './src/screens/Send';
import PayScreen      from './src/screens/Pay';
import HistoryScreen  from './src/screens/History';
import ProfileScreen  from './src/screens/Profile';
import KycScreen      from './src/screens/Kyc';
import NearbyProductsScreen from './src/screens/NearbyProducts';
import MyProductsScreen     from './src/screens/MyProducts';
import SplashIntroScreen    from './src/screens/SplashIntro';
import TipsOnboardingScreen from './src/screens/TipsOnboarding';
import WalletScreen         from './src/screens/Wallet';
import WithdrawScreen       from './src/screens/Withdraw';
import RequestMoneyScreen   from './src/screens/RequestMoney';
import AddPayoutAccountScreen from './src/screens/AddPayoutAccount';
import MyQrCodeScreen from './src/screens/MyQrCode';
import ScanQrPayScreen from './src/screens/ScanQrPay';
import BillsScreen from './src/screens/Bills';
import ProductDetailScreen from './src/screens/ProductDetail';
import CartScreen from './src/screens/Cart';
import CheckoutScreen from './src/screens/Checkout';
import BuyForMeScreen from './src/screens/BuyForMe';
import RequestBuyForMeScreen from './src/screens/RequestBuyForMe';
import BuyRequestsInboxScreen from './src/screens/BuyRequestsInbox';
import RewardsHubScreen from './src/screens/RewardsHub';
import PurchasedProductsScreen from './src/screens/PurchasedProducts';
import MessagesScreen from './src/screens/Messages';
import MessageThreadScreen from './src/screens/MessageThread';

validateApiConfig();

const Stack  = createNativeStackNavigator();
const Tab    = createBottomTabNavigator();
const Drawer = createDrawerNavigator();
const ONBOARDING_KEY = 'extracash_onboarding_seen_v1';

const TABS = [
  { name: 'Home',      lib: 'feather', icon: 'home',         label: 'Home' },
  { name: 'MyQrCode',  lib: 'mci',     icon: 'qrcode',       label: 'My QR' },
  { name: 'ScanQrPay', lib: 'mci',     icon: 'qrcode-scan',  label: 'Scan QR' },
  { name: 'Bills',     lib: 'feather', icon: 'file-text',    label: 'Bills' },
  { name: 'Profile',   lib: 'feather', icon: 'user',         label: 'Profile' },
];

function TabIcon({ tab, color }) {
  if (tab.lib === 'mci') {
    return <MaterialCommunityIcons name={tab.icon} size={20} color={color} />;
  }
  return <Feather name={tab.icon} size={20} color={color} />;
}

function CustomTabBar({ state, navigation }) {
  const insets = useSafeAreaInsets();
  // Android usually reports insets.bottom = 0 (no home indicator), so the
  // tab icons sit right on the edge. Give it a small breathing space.
  const minBottom = Platform.OS === 'android' ? 16 : 12;
  return (
    <View style={[styles.tabBar, { paddingBottom: insets.bottom + minBottom }]}>
      {state.routes.map((route, index) => {
        const focused = state.index === index;
        const tab = TABS[index];
        return (
          <TouchableOpacity
            key={route.key}
            onPress={() => navigation.navigate(route.name)}
            style={styles.tabItem}
            activeOpacity={0.7}
          >
            <View style={[styles.tabIconWrap, focused && styles.tabIconActive]}>
              <TabIcon tab={tab} color={focused ? '#fff' : '#9CA3AF'} />
            </View>
            <Text style={[styles.tabLabel, focused && styles.tabLabelActive]}>{tab.label}</Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

function AppTabs() {
  return (
    <Tab.Navigator tabBar={props => <CustomTabBar {...props} />} screenOptions={{ headerShown: false }}>
      <Tab.Screen name="Home"      component={HomeScreen} />
      <Tab.Screen name="MyQrCode"  component={MyQrCodeScreen} />
      <Tab.Screen name="ScanQrPay" component={ScanQrPayScreen} />
      <Tab.Screen name="Bills"     component={BillsScreen} />
      <Tab.Screen name="Profile"   component={ProfileScreen} />
    </Tab.Navigator>
  );
}

function MainDrawer() {
  return (
    <Drawer.Navigator
      drawerContent={props => <AppDrawerContent {...props} />}
      screenOptions={{
        headerShown: false,
        drawerType: 'slide',
        overlayColor: 'rgba(0,0,0,0.4)',
        drawerStyle: {
          width: '82%',
          maxWidth: 320,
          backgroundColor: '#FAFAFA',
        },
        sceneContainerStyle: { backgroundColor: '#F3F4F6' },
        swipeEnabled: true,
        swipeEdgeWidth: 56,
      }}
    >
      <Drawer.Screen name="MainTabs" component={AppTabs} options={{ title: 'ExtraCash' }} />
    </Drawer.Navigator>
  );
}

function RootNavigator() {
  const { user, loading } = useAuth();
  const [checkingOnboarding, setCheckingOnboarding] = useState(true);
  const [showOnboarding, setShowOnboarding] = useState(false);
  const [showSplash, setShowSplash] = useState(false);

  useEffect(() => {
    (async () => {
      try {
        const seen = await AsyncStorage.getItem(ONBOARDING_KEY);
        const needsOnboarding = !seen;
        setShowOnboarding(needsOnboarding);
        setShowSplash(needsOnboarding);
      } finally {
        setCheckingOnboarding(false);
      }
    })();
  }, []);

  const completeOnboarding = async () => {
    await AsyncStorage.setItem(ONBOARDING_KEY, '1');
    setShowOnboarding(false);
  };

  if (loading || checkingOnboarding) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#fff' }}>
        <ActivityIndicator size="large" color="#000" />
      </View>
    );
  }

  // Show intro flow only for non-authenticated users.
  if (!user && showOnboarding) {
    if (showSplash) {
      return <SplashIntroScreen onDone={() => setShowSplash(false)} />;
    }

    return <TipsOnboardingScreen onFinish={completeOnboarding} />;
  }

  // NOTE: We intentionally avoid `presentation: 'modal'` here.
  // iOS's native modal presentation blocks our DialogContext <Modal>
  // from appearing on top (UIKit refuses to present a new modal from a
  // VC that already has one presented). Using a slide-from-bottom card
  // animation keeps the modal-like feel while letting our global
  // confirmation dialogs render above every screen.
  const slideUp = { animation: 'slide_from_bottom' };

  return (
    <Stack.Navigator screenOptions={{ headerShown: false }}>
      {user ? (
        <>
          <Stack.Screen name="Main"              component={MainDrawer} />
          <Stack.Screen name="Wallet"            component={WalletScreen} />
          <Stack.Screen name="Kyc"               component={KycScreen} />
          <Stack.Screen name="NearbyProducts"    component={NearbyProductsScreen}    options={slideUp} />
          <Stack.Screen name="MyProducts"        component={MyProductsScreen}        options={slideUp} />
          <Stack.Screen name="ProductDetail"     component={ProductDetailScreen}     options={slideUp} />
          <Stack.Screen name="Cart"              component={CartScreen}              options={slideUp} />
          <Stack.Screen name="Checkout"          component={CheckoutScreen}          options={slideUp} />
          <Stack.Screen name="BuyForMe"          component={BuyForMeScreen}          options={slideUp} />
          <Stack.Screen name="RequestBuyForMe"   component={RequestBuyForMeScreen}   options={slideUp} />
          <Stack.Screen name="BuyRequestsInbox"  component={BuyRequestsInboxScreen}  options={slideUp} />
          <Stack.Screen name="PurchasedProducts" component={PurchasedProductsScreen} options={slideUp} />
          <Stack.Screen name="RewardsHub"        component={RewardsHubScreen}        options={slideUp} />
          <Stack.Screen name="Messages"          component={MessagesScreen}          options={slideUp} />
          <Stack.Screen name="MessageThread"     component={MessageThreadScreen}     options={slideUp} />
          <Stack.Screen name="Fund"              component={FundScreen}              options={slideUp} />
          <Stack.Screen name="Pay"               component={PayScreen}                options={slideUp} />
          <Stack.Screen name="Send"              component={SendScreen}              options={slideUp} />
          <Stack.Screen name="History"           component={HistoryScreen}           options={slideUp} />
          <Stack.Screen name="Withdraw"          component={WithdrawScreen}          options={slideUp} />
          <Stack.Screen name="RequestMoney"      component={RequestMoneyScreen}      options={slideUp} />
          <Stack.Screen name="AddPayoutAccount"  component={AddPayoutAccountScreen}  options={slideUp} />
        </>
      ) : (
        <>
          <Stack.Screen name="Login"    component={LoginScreen} />
          <Stack.Screen name="Register" component={RegisterScreen} />
          <Stack.Screen name="OtpVerify" component={OtpVerifyScreen} />
          <Stack.Screen name="ForgotPassword" component={ForgotPasswordScreen} />
          <Stack.Screen name="ResetPassword" component={ResetPasswordScreen} />
        </>
      )}
    </Stack.Navigator>
  );
}

export default function App() {
  const navRef = React.useRef(null);

  useEffect(() => {
    // Deep-link from push notifications to message thread when the user taps.
    const sub = Notifications.addNotificationResponseReceivedListener((response) => {
      const data = response?.notification?.request?.content?.data || {};
      if (data?.type === 'message_new' && data?.conversation_id) {
        navRef.current?.navigate('MessageThread', {
          conversationId: Number(data.conversation_id),
        });
      }
    });
    return () => sub.remove();
  }, []);

  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <SafeAreaProvider>
        <DialogProvider>
          <AuthProvider>
            <CartProvider>
              <StatusBar style="dark" backgroundColor="#fff" />
              <NavigationContainer ref={navRef}>
                <RootNavigator />
              </NavigationContainer>
            </CartProvider>
          </AuthProvider>
        </DialogProvider>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}

const styles = StyleSheet.create({
  tabBar: {
    flexDirection: 'row',
    backgroundColor: '#fff',
    paddingTop: 10,
    paddingBottom: 12,
    paddingHorizontal: 12,
    borderTopWidth: 1,
    borderTopColor: '#E5E7EB',
  },
  tabItem: { flex: 1, alignItems: 'center', gap: 4 },
  tabIconWrap: {
    width: 40, height: 32, borderRadius: 8,
    justifyContent: 'center', alignItems: 'center',
  },
  tabIconActive: { backgroundColor: '#000', borderRadius: 10 },
  tabLabel:      { fontSize: 11, color: '#9CA3AF' },
  tabLabelActive:{ color: '#000', fontWeight: '600' },
});
