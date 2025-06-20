import {ClientTypes} from "../../../domain/GemsResultsData"

export class DistributionCalculator {
    private static tokensMintingAmount = 5000

    static calculate(gemsData : ClientTypes.GemsResultsData) : ClientTypes.TokensDistibution {
        const tokensPerGem = DistributionCalculator.tokensMintingAmount / gemsData.totalGems

        const userGems = gemsData.data.map((user) => {
            const tokenAmount = tokensPerGem * user.gems
            return new ClientTypes.UserToken(
                user.userid,
                user.walletid,
                tokenAmount
            )
        })

        const result = new ClientTypes.TokensDistibution(
            DistributionCalculator.tokensMintingAmount,
            userGems
        )

        return result
    }
}