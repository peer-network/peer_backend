import { IPeerBackendClientAuthEndpoint } from '../../../../interfaces/endpoints/client/peerBackend/IPeerBackendClientEndpoint'
import { ClientTypes } from '../../../../domain/GemsResultsData';
import PeerBackendLogin from '../../../../utils/login'

export default class PeerBackendClientAuthEndpointImpl implements IPeerBackendClientAuthEndpoint {
    async login(): Promise<ClientTypes.LoginData> {
        const dto = await PeerBackendLogin.login()

        const entity = dto.toEntity()

        return entity
    }
}