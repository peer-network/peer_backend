import { ApolloServer } from '@apollo/server';
import { expressMiddleware } from '@apollo/server/express4';
import { ApolloServerPluginDrainHttpServer } from '@apollo/server/plugin/drainHttpServer';
import express from 'express';
import { createServer } from 'http';
import { makeExecutableSchema } from '@graphql-tools/schema';
import { WebSocketServer } from 'ws';
import { useServer } from 'graphql-ws/lib/use/ws';
import { PubSub } from 'graphql-subscriptions';
import bodyParser from 'body-parser';
import cors from 'cors';
import { readFileSync } from 'fs';
import path from 'path';
import pubsub from './infrastructure/redis/redis.js';
import { PubSubAsyncIterator } from 'graphql-redis-subscriptions/dist/pubsub-async-iterator';
import { print } from 'ioredis/built';
import { Resolvers } from './infrastructure/gql/generated-types/server/types-server';

// import resolvers from './resolvers-server/resolvers-server';
// import { baseConfig } from '../../config/config';

const schemaPath : string = "../../src/schema.graphql"
const typeDefs = readFileSync(schemaPath, 'utf8');
const PORT = 8080;

// A number that we'll increment over time to simulate subscription events

function resolveNewPost(payload: {newPost:any;}): string {
  if (typeof payload === 'string') {
    payload = JSON.parse(payload);
  }
  console.log(payload);
  console.log(payload["newPost"]);
  console.log(payload.newPost);
  return payload.newPost;
}

const resolvers: Resolvers = {
  Subscription: {
    newPost: {
      subscribe: () => pubsub.asyncIterator(["newPost"]),
      resolve: resolveNewPost, // extract string from object
    }
  }
};

// Create schema, which will be used separately by ApolloServer and
// the WebSocket server.
const schema = makeExecutableSchema({ typeDefs, resolvers });

// Create an Express app and HTTP server; we will attach the WebSocket
// server and the ApolloServer to this HTTP server.
const app = express();
const httpServer = createServer(app);

// Set up WebSocket server.
const wsServer = new WebSocketServer({
  server: httpServer,
  path: '/graphql',
});
const serverCleanup = useServer({ schema }, wsServer);

// Set up ApolloServer.
const server = new ApolloServer({
  schema,
  plugins: [
    // Proper shutdown for the HTTP server.
    ApolloServerPluginDrainHttpServer({ httpServer }),

    // Proper shutdown for the WebSocket server.
    {
      async serverWillStart() {
        return {
          async drainServer() {
            await serverCleanup.dispose();
          },
        };
      },
    },
  ],
});

await server.start();
app.use('/graphql', cors<cors.CorsRequest>(), bodyParser.json(), expressMiddleware(server));

// Now that our HTTP server is fully set up, actually listen.
httpServer.listen(PORT, () => {
  console.log(`🚀 Query endpoint ready at http://localhost:${PORT}/graphql`);
  console.log(`🚀 Subscription endpoint ready at ws://localhost:${PORT}/graphql`);
});

