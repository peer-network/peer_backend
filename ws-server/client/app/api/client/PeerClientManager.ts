import { ApolloClient, InMemoryCache,HttpLink,createHttpLink, NormalizedCacheObject } from '@apollo/client';
import { baseConfig } from '../../config/config';
import { setContext } from '@apollo/client/link/context';
import { jwtDecode } from "jwt-decode";

export enum TokenAction {
  none,
  refresh,
  login
}

export class PeerBackendApolloClientManager{
  client : ApolloClient<NormalizedCacheObject> = new ApolloClient({cache: new InMemoryCache()})

  private peerBackendAccessToken : string = ""
  private peerBackendRefreshToken : string = ""
  
  constructor(
    peerBackendAccessToken : string = "",
    peerBackendRefreshToken: string = ""
  ){
    this.peerBackendAccessToken = peerBackendAccessToken
    this.peerBackendRefreshToken = peerBackendRefreshToken
    this.updateClient()
  }

  private updateClient() {
    const authLink = setContext((_, { headers }) => {
      return {
        headers: {
          ...headers,
          authorization: `Bearer ${this.peerBackendAccessToken}`
        }
      }
    });
    
    this.client = new ApolloClient({
      link: authLink.concat(this.httpLink),
      cache: new InMemoryCache(),
    });
  }

  httpLink = createHttpLink({
    uri: baseConfig.GRAPHQL.PEER_BACKEND_GRAPHQL_ENDPOINT,
    fetch,
  });


  tokenActionNeeded(): TokenAction {
    const accessTokenDecoded = jwtDecode(this.peerBackendAccessToken)
    const refreshTokenDecoded = jwtDecode(this.peerBackendRefreshToken)
    const now = Date.now() 
    if(accessTokenDecoded && accessTokenDecoded.exp && accessTokenDecoded.exp > now) {
      return TokenAction.none
    } else {
      return TokenAction.login
    }
  }

  updateAccessToken(peerBackendAccessToken : string) {
    this.peerBackendAccessToken = peerBackendAccessToken
    this.updateClient()
  }
}