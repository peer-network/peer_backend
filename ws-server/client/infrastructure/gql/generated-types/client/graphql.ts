/* eslint-disable */
import { TypedDocumentNode as DocumentNode } from '@graphql-typed-document-node/core';
export type Maybe<T> = T | null;
export type InputMaybe<T> = Maybe<T>;
export type Exact<T extends { [key: string]: unknown }> = { [K in keyof T]: T[K] };
export type MakeOptional<T, K extends keyof T> = Omit<T, K> & { [SubKey in K]?: Maybe<T[SubKey]> };
export type MakeMaybe<T, K extends keyof T> = Omit<T, K> & { [SubKey in K]: Maybe<T[SubKey]> };
export type MakeEmpty<T extends { [key: string]: unknown }, K extends keyof T> = { [_ in K]?: never };
export type Incremental<T> = T | { [P in keyof T]?: P extends ' $fragmentName' | '__typename' ? T[P] : never };
/** All built-in and custom scalars, mapped to their actual values */
export type Scalars = {
  ID: { input: string; output: string; }
  String: { input: string; output: string; }
  Boolean: { input: boolean; output: boolean; }
  Int: { input: number; output: number; }
  Float: { input: number; output: number; }
  Date: { input: any; output: any; }
  Decimal: { input: any; output: any; }
};

export type DailyGemStatusData = {
  __typename?: 'DailyGemStatusData';
  d0?: Maybe<Scalars['Decimal']['output']>;
  d1?: Maybe<Scalars['Decimal']['output']>;
  d2?: Maybe<Scalars['Decimal']['output']>;
  d3?: Maybe<Scalars['Decimal']['output']>;
  d4?: Maybe<Scalars['Decimal']['output']>;
  d5?: Maybe<Scalars['Decimal']['output']>;
  m0?: Maybe<Scalars['Decimal']['output']>;
  w0?: Maybe<Scalars['Decimal']['output']>;
  y0?: Maybe<Scalars['Decimal']['output']>;
};

export type DailyGemStatusResponse = {
  __typename?: 'DailyGemStatusResponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  affectedRows?: Maybe<DailyGemStatusData>;
  status: Scalars['String']['output'];
};

export type DailyGemsResultsData = {
  __typename?: 'DailyGemsResultsData';
  data?: Maybe<Array<Maybe<DailyGemsResultsUserData>>>;
  totalGems?: Maybe<Scalars['Decimal']['output']>;
};

export type DailyGemsResultsResponse = {
  __typename?: 'DailyGemsResultsResponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  affectedRows?: Maybe<DailyGemsResultsData>;
  status: Scalars['String']['output'];
};

export type DailyGemsResultsUserData = {
  __typename?: 'DailyGemsResultsUserData';
  gems?: Maybe<Scalars['Decimal']['output']>;
  pkey?: Maybe<Scalars['ID']['output']>;
  userid?: Maybe<Scalars['ID']['output']>;
};

export enum DayFilterType {
  D0 = 'D0',
  D1 = 'D1',
  D2 = 'D2',
  D3 = 'D3',
  D4 = 'D4',
  D5 = 'D5',
  M0 = 'M0',
  W0 = 'W0',
  Y0 = 'Y0'
}

export type HelloResponse = {
  __typename?: 'HelloResponse';
  currentVersion?: Maybe<Scalars['String']['output']>;
  currentuserid?: Maybe<Scalars['ID']['output']>;
  wikiLink?: Maybe<Scalars['String']['output']>;
};

export type Query = {
  __typename?: 'Query';
  dailygemsresults: DailyGemsResultsResponse;
  dailygemstatus: DailyGemStatusResponse;
  hello: HelloResponse;
};


export type QueryDailygemsresultsArgs = {
  day: DayFilterType;
};

export type HelloQueryVariables = Exact<{ [key: string]: never; }>;


export type HelloQuery = { __typename?: 'Query', hello: { __typename?: 'HelloResponse', currentuserid?: string | null, currentVersion?: string | null, wikiLink?: string | null } };

export type DailygemsresultsQueryVariables = Exact<{
  day: DayFilterType;
}>;


export type DailygemsresultsQuery = { __typename?: 'Query', dailygemsresults: { __typename?: 'DailyGemsResultsResponse', status: string, ResponseCode?: string | null, affectedRows?: { __typename?: 'DailyGemsResultsData', totalGems?: any | null, data?: Array<{ __typename?: 'DailyGemsResultsUserData', userid?: string | null, pkey?: string | null, gems?: any | null } | null> | null } | null } };

export type DailygemsstatusQueryVariables = Exact<{ [key: string]: never; }>;


export type DailygemsstatusQuery = { __typename?: 'Query', dailygemstatus: { __typename?: 'DailyGemStatusResponse', status: string, ResponseCode?: string | null, affectedRows?: { __typename?: 'DailyGemStatusData', d0?: any | null, d1?: any | null, d2?: any | null, d3?: any | null, d4?: any | null, d5?: any | null, w0?: any | null, m0?: any | null, y0?: any | null } | null } };


export const HelloDocument = {"kind":"Document","definitions":[{"kind":"OperationDefinition","operation":"query","name":{"kind":"Name","value":"Hello"},"selectionSet":{"kind":"SelectionSet","selections":[{"kind":"Field","name":{"kind":"Name","value":"hello"},"selectionSet":{"kind":"SelectionSet","selections":[{"kind":"Field","name":{"kind":"Name","value":"currentuserid"}},{"kind":"Field","name":{"kind":"Name","value":"currentVersion"}},{"kind":"Field","name":{"kind":"Name","value":"wikiLink"}}]}}]}}]} as unknown as DocumentNode<HelloQuery, HelloQueryVariables>;
export const DailygemsresultsDocument = {"kind":"Document","definitions":[{"kind":"OperationDefinition","operation":"query","name":{"kind":"Name","value":"dailygemsresults"},"variableDefinitions":[{"kind":"VariableDefinition","variable":{"kind":"Variable","name":{"kind":"Name","value":"day"}},"type":{"kind":"NonNullType","type":{"kind":"NamedType","name":{"kind":"Name","value":"DayFilterType"}}}}],"selectionSet":{"kind":"SelectionSet","selections":[{"kind":"Field","name":{"kind":"Name","value":"dailygemsresults"},"arguments":[{"kind":"Argument","name":{"kind":"Name","value":"day"},"value":{"kind":"Variable","name":{"kind":"Name","value":"day"}}}],"selectionSet":{"kind":"SelectionSet","selections":[{"kind":"Field","name":{"kind":"Name","value":"status"}},{"kind":"Field","name":{"kind":"Name","value":"ResponseCode"}},{"kind":"Field","name":{"kind":"Name","value":"affectedRows"},"selectionSet":{"kind":"SelectionSet","selections":[{"kind":"Field","name":{"kind":"Name","value":"totalGems"}},{"kind":"Field","name":{"kind":"Name","value":"data"},"selectionSet":{"kind":"SelectionSet","selections":[{"kind":"Field","name":{"kind":"Name","value":"userid"}},{"kind":"Field","name":{"kind":"Name","value":"pkey"}},{"kind":"Field","name":{"kind":"Name","value":"gems"}}]}}]}}]}}]}}]} as unknown as DocumentNode<DailygemsresultsQuery, DailygemsresultsQueryVariables>;
export const DailygemsstatusDocument = {"kind":"Document","definitions":[{"kind":"OperationDefinition","operation":"query","name":{"kind":"Name","value":"dailygemsstatus"},"selectionSet":{"kind":"SelectionSet","selections":[{"kind":"Field","name":{"kind":"Name","value":"dailygemstatus"},"selectionSet":{"kind":"SelectionSet","selections":[{"kind":"Field","name":{"kind":"Name","value":"status"}},{"kind":"Field","name":{"kind":"Name","value":"ResponseCode"}},{"kind":"Field","name":{"kind":"Name","value":"affectedRows"},"selectionSet":{"kind":"SelectionSet","selections":[{"kind":"Field","name":{"kind":"Name","value":"d0"}},{"kind":"Field","name":{"kind":"Name","value":"d1"}},{"kind":"Field","name":{"kind":"Name","value":"d2"}},{"kind":"Field","name":{"kind":"Name","value":"d3"}},{"kind":"Field","name":{"kind":"Name","value":"d4"}},{"kind":"Field","name":{"kind":"Name","value":"d5"}},{"kind":"Field","name":{"kind":"Name","value":"w0"}},{"kind":"Field","name":{"kind":"Name","value":"m0"}},{"kind":"Field","name":{"kind":"Name","value":"y0"}}]}}]}}]}}]} as unknown as DocumentNode<DailygemsstatusQuery, DailygemsstatusQueryVariables>;