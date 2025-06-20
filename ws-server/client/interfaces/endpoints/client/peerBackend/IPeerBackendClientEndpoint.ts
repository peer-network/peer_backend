import { ClientTypes } from '../../../../domain/GemsResultsData'

export interface IPeerBackendClientEndpoint {
  getHello(): Promise<ClientTypes.HelloData>;
  getPeerDailyGemsResults(day : ClientTypes.DayFilterType) : Promise<ClientTypes.GemsResultsData>
}

export interface IPeerBackendClientAuthEndpoint {
  login(): Promise<ClientTypes.LoginData>;
}
