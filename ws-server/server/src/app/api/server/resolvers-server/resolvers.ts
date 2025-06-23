import { Resolvers } from '../../../../infrastructure/gql/generated-types/server/types-server';
import { PubSub } from 'graphql-subscriptions';

const pubsub = new PubSub();

export const resolvers = {
  Subscription: {
    numberIncremented: {
      subscribe: () => pubsub.asyncIterator(['NUMBER_INCREMENTED']),
    },
    getBtcTokenPrice: {
      subscribe: async function* () {
        for await (const word of ['Hello', 'Bonjour', 'Ciao']) {
          yield { getBtcTokenPrice: word };
        }
      },
    },
  },
};