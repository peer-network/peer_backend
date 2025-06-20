import { IPeerBackendAuthRepository } from "../../interfaces/repos/IPeerBackendAuthRepository";
import { IPeerBackendClientAuthEndpoint } from "../../interfaces/endpoints/client/peerBackend/IPeerBackendClientEndpoint";
import { ClientTypes } from '../../domain/GemsResultsData';

export class PeerBackendAuthRepositoryImpl implements IPeerBackendAuthRepository {
    private peerBackendAuthEnpoint : IPeerBackendClientAuthEndpoint
    
    constructor(peerBackendAuthEnpoint : IPeerBackendClientAuthEndpoint) {
        this.peerBackendAuthEnpoint = peerBackendAuthEnpoint
    }
    async getTokenData(): Promise<ClientTypes.LoginData> {
        return await this.peerBackendAuthEnpoint.login()
    }
}