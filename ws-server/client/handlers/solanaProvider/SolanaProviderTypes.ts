import { TokenDistribution } from "../../../../peer_rust_backend/peer-token/app/mockdata/distribution";


export class DataGetGemsForDayResponseUserTokens implements TokenDistribution {
    constructor(
        readonly data: GetGemsForDayResponseUserTokens
    ) {}
}

export class GetGemsForDayResponseUserTokens {
    constructor(
        readonly GetGemsForDay: ResponseUserTokens 
    ) {}
}

export class ResponseUserTokens {
    constructor(
        readonly status: string,
        readonly ResponseCode: string,
        readonly Date: string,
        readonly affectedRows: DataUserTokens
    ) {}
}

export class DataUserTokens {
    constructor(
        readonly data: UserTokens[],
        readonly totalTokens: number
    ) {}
}

export class UserTokens {
    constructor(
        readonly userId: string,
        readonly walletAddress: string,
        readonly tokens: number,
    ) {}
}
