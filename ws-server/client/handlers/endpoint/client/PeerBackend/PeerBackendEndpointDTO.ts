import { DailyGemStatusResponse,DailyGemStatusData, HelloQuery, DailygemsresultsQuery } from '../../../../infrastructure/gql/generated-types/client/graphql'
import { ClientTypes } from '../../../../domain/GemsResultsData'
// import {
//     HellloDTOValidationSchema, 
//     LoginDTOValidationSchema,
//     DailyGemsResultsDataDTOValidationSchema,
//     DailyGemsResultsUserDTOValidationSchema
// } from "../../../endpoint/client/PeerBackendAuth/PeerBackendAuthDTOValidator"

export namespace PeerBackendDTO {
    export class LoginDTO {
        constructor(
            public status : string,
            public ResponseCode : string,
            public accessToken : string | undefined,
            public refreshToken : string | undefined
        ) {}

        toEntity(): ClientTypes.LoginData {
            return new ClientTypes.LoginData(
                this.status,
                this.ResponseCode,
                this.accessToken,
                this.refreshToken
            )
        }
    }

    export enum DayFilterType {
        D0 = 'D0',
        D1 = 'D1',
        D2 = 'D2',
        D3 = 'D3',
        D4 = 'D4',
        D5 = 'D5',
        M0 = 'M0',
        W0 = 'W0',
        Y0 = 'Y0'
      }
      
    export class HelloDTO {
        constructor(
            public currentVersion: string | null | undefined,
            public currentuserid: string| null | undefined,
            public wikiLink: string | null | undefined
        ) {}
        
        toEntity(): ClientTypes.HelloData {
            return new ClientTypes.HelloData(
                this.currentVersion!,
                this.currentuserid,
                this.wikiLink!
            )
        }
    };

    export class GetDailyGemsResultsResponse {
        constructor(
            public responseCode: string | null | undefined,
            public data: GetDailyGemsResultsData | null | undefined,
            public status: string | null | undefined,
        ) {}
        
    }
    
    export class GetDailyGemsResultsData {
        constructor(
            public data : GetDailyGemsResultsUserGems[],
            public totalGems : string
        ) {}

        toEntity(): ClientTypes.GemsResultsData {
            // DailyGemsResultsDataDTOValidationSchema.parse(this)
            
            const totalGems = Number(this.totalGems)
            return new ClientTypes.GemsResultsData(
                this.data.map((user) => {
                    return user.toEntity()
                }),
                totalGems
            )
        }
    }

    export class GetDailyGemsResultsUserGems {
        constructor(
            public userid : string,
            public pkey : string,
            public gems : string
        ){}

        toEntity(): ClientTypes.GemsResultsUserGems {
            const gems = Number(this.gems)
            return new ClientTypes.GemsResultsUserGems(
                this.userid!,
                this.pkey!,
                gems
            )
        }
    }



    export class GetDailyGemsStatusResponse{
        constructor(response : DailyGemStatusResponse ) {
            this.responseCode = response.ResponseCode
            this.data = new GetDailyGemsStatusData(response.affectedRows)
            this.status = response.status
        }
        responseCode: string | null | undefined
        data: GetDailyGemsStatusData | null | undefined
        status: string | null | undefined
    }

    export class GetDailyGemsStatusData{
        constructor(data : DailyGemStatusData | undefined | null) {
            this.data = data

        }
        data : any
    }
}