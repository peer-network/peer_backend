import { ClientTypes } from '../../domain/GemsResultsData';

export interface IPeerBackendAuthRepository {
    getTokenData(): Promise<ClientTypes.LoginData>
}