import { RedisPubSub } from 'graphql-redis-subscriptions';
import Redis from 'ioredis';

const options = {
  host: '127.0.0.1',
  port: 6379,
  // password: '30904622-5260-4f77-ab88-2660c19cd3e9', // if used
};

const pubsub = new RedisPubSub({
  publisher: new Redis(options),
  subscriber: new Redis(options),
});

export default pubsub;