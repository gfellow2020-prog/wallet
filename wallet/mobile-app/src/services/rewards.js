import api from './client';

export async function checkInRewards() {
  const res = await api.post('/rewards/check-in');
  return res.data;
}

export async function fetchRewardsSummary() {
  const res = await api.get('/rewards/summary');
  return res.data;
}

export async function fetchRewardsMissions() {
  const res = await api.get('/rewards/missions');
  return res.data;
}

export async function claimRewardMission(missionId) {
  const res = await api.post(`/rewards/missions/${missionId}/claim`);
  return res.data;
}

export function describeReward(reward = {}) {
  if (!reward) return 'Reward';

  const type = reward.type ?? reward.reward_type;
  const value = reward.value ?? reward.reward_value;

  if (type === 'wallet_bonus') {
    const amount = Number(value || reward.meta?.wallet_amount || 0);
    return `ZMW ${amount.toFixed(2)} bonus`;
  }

  if (type === 'badge_unlock') {
    return reward.meta?.badge_title || reward.meta?.label || value || 'Badge unlocked';
  }

  return reward.meta?.label || value || 'Reward';
}
