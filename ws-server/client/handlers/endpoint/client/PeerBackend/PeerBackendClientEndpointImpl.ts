import { gql } from '../../../../infrastructure/gql/generated-types/client/gql';
import { IPeerBackendClientEndpoint } from '../../../../interfaces/endpoints/client/peerBackend/IPeerBackendClientEndpoint'
import { DAILY_GEMS_RESULTS_REQUEST, DAILY_GEMS_STATUS_REQUEST,HELLO_REQUREST}   from "../../../../infrastructure/gql/queries/queries"
import {clientManager} from '../../../../app/api/client/client'
import { PeerBackendDTO } from './PeerBackendEndpointDTO';
import { ClientTypes } from '../../../../domain/GemsResultsData';
import { ClientExceptionImpl } from '../../../../utils/errors/ClientException';
import { ClientErrorCases } from '../../../../utils/errors/IClientErrorCases';


export default class PeerBackendClientEndpointImpl implements IPeerBackendClientEndpoint {
    
    async getHello() : Promise<ClientTypes.HelloData> {
        const response = await clientManager.client.query({
            query: gql(HELLO_REQUREST),
            variables: {},
        });
        const dto = new PeerBackendDTO.HelloDTO(
            response.data.hello.currentVersion,
            response.data.hello.currentuserid,
            response.data.hello.wikiLink,
        )
        const entity = dto.toEntity()

        return entity
    }

    async getPeerDailyGemsResults(day : ClientTypes.DayFilterType) : Promise<ClientTypes.GemsResultsData> {
        const response = await clientManager.client.query({
            query: gql(DAILY_GEMS_RESULTS_REQUEST),
            variables: {day},
        });

        const dto = response.data.dailygemsresults.affectedRows
        console.log(response.data.dailygemsresults)
        if (!dto) {
            throw new ClientExceptionImpl(ClientErrorCases.ObjectContentsIsInvalid, "PeerBackendClientEndpointImpl: getPeerDailyGemsResults: data is NULL")
        }

        const totalGems = dto.totalGems
        const userGemsRaw = dto.data as Array<any> 

        if (!userGemsRaw) {
            throw new ClientExceptionImpl(ClientErrorCases.ObjectContentsIsInvalid, "PeerBackendClientEndpointImpl: getPeerDailyGemsResults: data is NULL")
        }

        const userGems = userGemsRaw.map((user) => {
            return new PeerBackendDTO.GetDailyGemsResultsUserGems(
                user.userid, 
                user.pkey, 
                user.gems
            )
        })

        if (!userGems || !totalGems) {
            throw new ClientExceptionImpl(ClientErrorCases.ObjectContentsIsInvalid, "PeerBackendClientEndpointImpl: getPeerDailyGemsResults: data is NULL")
        }

        const gemsData = new PeerBackendDTO.GetDailyGemsResultsData(
            userGems,
            totalGems
        )

        const entity = gemsData.toEntity()
        return entity
    }

    // async getPeerDailyGemsStatus() : Promise<PeerBackendDTO.GetDailyGemsStatusResponse> {
    //     const repsonse = await clientManager.client.query({
    //         query: gql(DAILY_GEMS_STATUS_REQUEST),
    //         variables: {},
    //     });
    //     const data = new PeerBackendDTO.GetDailyGemsStatusResponse(repsonse.data.dailygemstatus)
    //     return EmptyOrNullValidator.validateAndGet<PeerBackendDTO.GetDailyGemsStatusResponse>(data)
//   }
}