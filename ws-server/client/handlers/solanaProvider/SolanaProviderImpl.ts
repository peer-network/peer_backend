import { ISolanaProvider } from "../../interfaces/solana/ISolanaProvider"
import {main} from "../../../../peer_rust_backend/peer-token/app/token_operations/user_token"
import {mint} from "../../../../peer_rust_backend/peer-token/app/token_operations/mint_to_company_daily"

import {ClientTypes} from "../../domain/GemsResultsData"
import {TokenDistribution} from "../../../../peer_rust_backend/peer-token/app/mockdata/distribution"
import {DataGetGemsForDayResponseUserTokens,GetGemsForDayResponseUserTokens,ResponseUserTokens,DataUserTokens,UserTokens}  from "./SolanaProviderTypes"
import {MintResponse,AirDropResponse}  from "./SolanaProviderResponse"

export class SolanaRPCProviderImpl implements ISolanaProvider {
    async mint() : Promise<MintResponse> {
        return await mint()
    }
    async airdrop(data: ClientTypes.TokensDistibution) {
        // const gemData = new DataGetGemsForDayResponseUserTokens(
        //     new GetGemsForDayResponseUserTokens(
        //         new ResponseUserTokens(
        //             "status",
        //             "10000",
        //             "1",
        //             new DataUserTokens(
        //                 data.userTokens.map((user)=>{
        //                     return new UserTokens(
        //                         user.userid,
        //                         user.walletid,
        //                         user.tokenAmount,
        //                     )
        //                 }),
        //                 data.totalAmount
        //             )
        //         )
        //     )
        // )
        // await main(gemData)
    }
}