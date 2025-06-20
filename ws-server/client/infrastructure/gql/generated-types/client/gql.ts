/* eslint-disable */
import * as types from './graphql';
import { TypedDocumentNode as DocumentNode } from '@graphql-typed-document-node/core';

/**
 * Map of all GraphQL operations in the project.
 *
 * This map has several performance disadvantages:
 * 1. It is not tree-shakeable, so it will include all operations in the project.
 * 2. It is not minifiable, so the string of a GraphQL query will be multiple times inside the bundle.
 * 3. It does not support dead code elimination, so it will add unused operations.
 *
 * Therefore it is highly recommended to use the babel or swc plugin for production.
 * Learn more about it here: https://the-guild.dev/graphql/codegen/plugins/presets/preset-client#reducing-bundle-size
 */
type Documents = {
    "\n    query Hello {\n        hello {\n            currentuserid\n            currentVersion\n            wikiLink\n        }\n    }\n": typeof types.HelloDocument,
    "\n    query dailygemsresults($day: DayFilterType!)  {\n        dailygemsresults(day: $day) {\n            status\n            ResponseCode\n            affectedRows {\n                totalGems\n                data {\n                    userid\n                    pkey\n                    gems\n                }\n            }\n        }\n    }\n": typeof types.DailygemsresultsDocument,
    "\n    query dailygemsstatus {\n        dailygemstatus {\n            status\n            ResponseCode\n            affectedRows {\n                d0\n                d1\n                d2\n                d3\n                d4\n                d5\n                w0\n                m0\n                y0\n            }\n        }\n    }\n": typeof types.DailygemsstatusDocument,
};
const documents: Documents = {
    "\n    query Hello {\n        hello {\n            currentuserid\n            currentVersion\n            wikiLink\n        }\n    }\n": types.HelloDocument,
    "\n    query dailygemsresults($day: DayFilterType!)  {\n        dailygemsresults(day: $day) {\n            status\n            ResponseCode\n            affectedRows {\n                totalGems\n                data {\n                    userid\n                    pkey\n                    gems\n                }\n            }\n        }\n    }\n": types.DailygemsresultsDocument,
    "\n    query dailygemsstatus {\n        dailygemstatus {\n            status\n            ResponseCode\n            affectedRows {\n                d0\n                d1\n                d2\n                d3\n                d4\n                d5\n                w0\n                m0\n                y0\n            }\n        }\n    }\n": types.DailygemsstatusDocument,
};

/**
 * The gql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 *
 *
 * @example
 * ```ts
 * const query = gql(`query GetUser($id: ID!) { user(id: $id) { name } }`);
 * ```
 *
 * The query argument is unknown!
 * Please regenerate the types.
 */
export function gql(source: string): unknown;

/**
 * The gql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function gql(source: "\n    query Hello {\n        hello {\n            currentuserid\n            currentVersion\n            wikiLink\n        }\n    }\n"): (typeof documents)["\n    query Hello {\n        hello {\n            currentuserid\n            currentVersion\n            wikiLink\n        }\n    }\n"];
/**
 * The gql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function gql(source: "\n    query dailygemsresults($day: DayFilterType!)  {\n        dailygemsresults(day: $day) {\n            status\n            ResponseCode\n            affectedRows {\n                totalGems\n                data {\n                    userid\n                    pkey\n                    gems\n                }\n            }\n        }\n    }\n"): (typeof documents)["\n    query dailygemsresults($day: DayFilterType!)  {\n        dailygemsresults(day: $day) {\n            status\n            ResponseCode\n            affectedRows {\n                totalGems\n                data {\n                    userid\n                    pkey\n                    gems\n                }\n            }\n        }\n    }\n"];
/**
 * The gql function is used to parse GraphQL queries into a document that can be used by GraphQL clients.
 */
export function gql(source: "\n    query dailygemsstatus {\n        dailygemstatus {\n            status\n            ResponseCode\n            affectedRows {\n                d0\n                d1\n                d2\n                d3\n                d4\n                d5\n                w0\n                m0\n                y0\n            }\n        }\n    }\n"): (typeof documents)["\n    query dailygemsstatus {\n        dailygemstatus {\n            status\n            ResponseCode\n            affectedRows {\n                d0\n                d1\n                d2\n                d3\n                d4\n                d5\n                w0\n                m0\n                y0\n            }\n        }\n    }\n"];

export function gql(source: string) {
  return (documents as any)[source] ?? {};
}

export type DocumentType<TDocumentNode extends DocumentNode<any, any>> = TDocumentNode extends DocumentNode<  infer TType,  any>  ? TType  : never;