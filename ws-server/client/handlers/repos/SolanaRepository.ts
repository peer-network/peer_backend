import { CodeDescription } from "../../utils/errors/types";
import { Result } from "../../domain/Result";
import { Status } from "../../handlers/solanaProvider/SolanaProviderResponse";
import { ISolanaRepository } from "../../interfaces/repos/ISolanaRepository";
import { ISolanaProvider } from "../../interfaces/solana/ISolanaProvider";
import { error } from "console";

export class SolanaRepository implements ISolanaRepository {
    private solanaProvider : ISolanaProvider

    constructor(solanaProvider : ISolanaProvider) {
        this.solanaProvider = solanaProvider
    }
    // async airdrop(data: any): Promise<Array<any> | undefined> {
    async airdrop(data: any) {
        return await this.solanaProvider.airdrop(data)
    }

    async mint(): Promise<Result<undefined,CodeDescription>> {
        const response = await this.solanaProvider.mint()

        if (response.status == Status.error) {
            return Result.fail({
                code: response.code,
                message: response.message
            })
        }
        return Result.succeed(undefined)
    }
}
