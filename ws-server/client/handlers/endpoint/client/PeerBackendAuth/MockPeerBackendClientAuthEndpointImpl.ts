import { IPeerBackendClientAuthEndpoint } from '../../../../interfaces/endpoints/client/peerBackend/IPeerBackendClientEndpoint'
import { ClientTypes } from '../../../../domain/GemsResultsData';

export default class MockPeerBackendClientAuthEndpointImpl implements IPeerBackendClientAuthEndpoint {
    async login(): Promise<ClientTypes.LoginData> {
        return new ClientTypes.LoginData("mock-data","mock-data","mock-data","mock-data")
    }
}