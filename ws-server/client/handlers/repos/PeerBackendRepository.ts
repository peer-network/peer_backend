import { IBackendRepository } from "../../interfaces/repos/IPeerBackendRepository";
import { ClientTypes } from "../../domain/GemsResultsData";
import { IPeerBackendClientEndpoint, IPeerBackendClientAuthEndpoint } from "../../interfaces/endpoints/client/peerBackend/IPeerBackendClientEndpoint";
import PeerBackendClientAuthEndpointImpl from "../endpoint/client/PeerBackendAuth/PeerBackendClientAuthEndpointImpl";
import PeerBackendClientEndpointImpl from "../endpoint/client/PeerBackend/PeerBackendClientEndpointImpl";
import { EmptyOrNullValidator } from "../../utils/validation/NullOrEmptyValidation";

export class PeerBackendRepositoryImpl implements IBackendRepository {
    private peerBackendEnpoint : IPeerBackendClientEndpoint
    private peerBackendAuthEnpoint : IPeerBackendClientAuthEndpoint
    
    constructor(
        peerBackendEnpoint : IPeerBackendClientEndpoint = new PeerBackendClientEndpointImpl(),
        peerBackendAuthEnpoint : PeerBackendClientAuthEndpointImpl = new PeerBackendClientAuthEndpointImpl(),
    ) {
        this.peerBackendEnpoint = peerBackendEnpoint
        this.peerBackendAuthEnpoint = peerBackendAuthEnpoint
    }
    async getHelloData(): Promise<ClientTypes.HelloData> {
        return await this.peerBackendEnpoint.getHello()
    }
    async getDailyGemsResultData(day: ClientTypes.DayFilterType): Promise<ClientTypes.GemsResultsData> {
        const response = await this.peerBackendEnpoint.getPeerDailyGemsResults(day)
        const typedResponse = EmptyOrNullValidator.validateAndGet<ClientTypes.GemsResultsData>(response)
        return typedResponse
    }
}