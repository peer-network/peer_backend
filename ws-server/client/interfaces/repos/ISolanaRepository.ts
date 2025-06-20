import { ClientTypes } from "../../domain/GemsResultsData"
import { MintResponse } from "../../handlers/solanaProvider/SolanaProviderResponse"
import { Result } from "../../domain/Result"
import { CodeDescription } from "../../utils/errors/types"

export interface ISolanaRepository {
    mint(): Promise<Result<undefined,CodeDescription>>
    airdrop(data: ClientTypes.TokensDistibution): void
}