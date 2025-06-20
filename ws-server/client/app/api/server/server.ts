import { readFileSync } from 'fs';
import path from 'path';
import resolvers from './resolvers-server/resolvers-server';
import { baseConfig } from '../../config/config';
import { ApolloServer } from 'apollo-server';
import { logger } from '../../../utils/logger';

const schemaPath : string = "./schema.graphql"
const typeDefs = readFileSync(path.join(__dirname, schemaPath), 'utf8');

const server = new ApolloServer({
  typeDefs,
  resolvers,
});

server.listen({ port: baseConfig.GRAPHQL.APP_PORT }).then(({ url }) => {
  logger.info(`🚀 Server ready at ${url}`);
});