import { GraphQLResolveInfo, GraphQLScalarType, GraphQLScalarTypeConfig } from 'graphql';
export type Maybe<T> = T | null;
export type InputMaybe<T> = Maybe<T>;
export type Exact<T extends { [key: string]: unknown }> = { [K in keyof T]: T[K] };
export type MakeOptional<T, K extends keyof T> = Omit<T, K> & { [SubKey in K]?: Maybe<T[SubKey]> };
export type MakeMaybe<T, K extends keyof T> = Omit<T, K> & { [SubKey in K]: Maybe<T[SubKey]> };
export type MakeEmpty<T extends { [key: string]: unknown }, K extends keyof T> = { [_ in K]?: never };
export type Incremental<T> = T | { [P in keyof T]?: P extends ' $fragmentName' | '__typename' ? T[P] : never };
export type RequireFields<T, K extends keyof T> = Omit<T, K> & { [P in K]-?: NonNullable<T[P]> };
/** All built-in and custom scalars, mapped to their actual values */
export type Scalars = {
  ID: { input: string; output: string; }
  String: { input: string; output: string; }
  Boolean: { input: boolean; output: boolean; }
  Int: { input: number; output: number; }
  Float: { input: number; output: number; }
  Date: { input: any; output: any; }
  Decimal: { input: any; output: any; }
};

export type ActionPriceResult = {
  __typename?: 'ActionPriceResult';
  commentPrice: Scalars['Float']['output'];
  dislikePrice: Scalars['Float']['output'];
  likePrice: Scalars['Float']['output'];
  postPrice: Scalars['Float']['output'];
};

export type AddChatResponse = {
  __typename?: 'AddChatResponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  affectedRows?: Maybe<Chatinfo>;
  status: Scalars['String']['output'];
};

export type AddChatmessageResponse = {
  __typename?: 'AddChatmessageResponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  affectedRows?: Maybe<Array<Maybe<ChatMessageInfo>>>;
  counter: Scalars['Int']['output'];
  status: Scalars['String']['output'];
};

export type AddPostResponse = {
  __typename?: 'AddPostResponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  affectedRows?: Maybe<Postinfo>;
  status: Scalars['String']['output'];
};

export type BlockedUser = {
  __typename?: 'BlockedUser';
  img?: Maybe<Scalars['String']['output']>;
  slug?: Maybe<Scalars['Int']['output']>;
  userid?: Maybe<Scalars['String']['output']>;
  username?: Maybe<Scalars['String']['output']>;
};

export type BlockedUsers = {
  __typename?: 'BlockedUsers';
  blockedBy?: Maybe<Array<BlockedUser>>;
  iBlocked?: Maybe<Array<BlockedUser>>;
};

export type BlockedUsersResponse = {
  __typename?: 'BlockedUsersResponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  affectedRows?: Maybe<BlockedUsers>;
  counter: Scalars['Int']['output'];
  status?: Maybe<Scalars['String']['output']>;
};

export type BtcTokenPriceResponse = {
  __typename?: 'BtcTokenPriceResponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  btcTokenPrice?: Maybe<Scalars['String']['output']>;
  status: Scalars['String']['output'];
};

export type Chat = {
  __typename?: 'Chat';
  chatmessages: Array<ChatMessage>;
  chatparticipants: Array<ChatParticipant>;
  createdat?: Maybe<Scalars['Date']['output']>;
  id: Scalars['ID']['output'];
  image: Scalars['String']['output'];
  name: Scalars['String']['output'];
  updatedat?: Maybe<Scalars['Date']['output']>;
};

export type ChatInput = {
  image?: InputMaybe<Scalars['String']['input']>;
  name: Scalars['String']['input'];
  recipients: Array<Scalars['String']['input']>;
};

export type ChatMessage = {
  __typename?: 'ChatMessage';
  chatid: Scalars['ID']['output'];
  content: Scalars['String']['output'];
  createdat: Scalars['Date']['output'];
  id: Scalars['ID']['output'];
  senderid: Scalars['ID']['output'];
};

export type ChatMessageInfo = {
  __typename?: 'ChatMessageInfo';
  chatid?: Maybe<Scalars['ID']['output']>;
  content?: Maybe<Scalars['String']['output']>;
  createdat?: Maybe<Scalars['Date']['output']>;
  messid?: Maybe<Scalars['ID']['output']>;
  userid?: Maybe<Scalars['ID']['output']>;
};

export type ChatParticipant = {
  __typename?: 'ChatParticipant';
  hasaccess: Scalars['Int']['output'];
  img: Scalars['String']['output'];
  slug: Scalars['Int']['output'];
  userid: Scalars['ID']['output'];
  username: Scalars['String']['output'];
};

export type ChatParticipantsInput = {
  chatid: Scalars['ID']['input'];
  recipients: Array<Scalars['String']['input']>;
};

export type ChatResponse = {
  __typename?: 'ChatResponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  affectedRows?: Maybe<Array<Chat>>;
  counter: Scalars['Int']['output'];
  status: Scalars['String']['output'];
};

export type Chatinfo = {
  __typename?: 'Chatinfo';
  chatid?: Maybe<Scalars['ID']['output']>;
};

export type Comment = {
  __typename?: 'Comment';
  amountlikes: Scalars['Int']['output'];
  amountreplies: Scalars['Int']['output'];
  commentid: Scalars['ID']['output'];
  content: Scalars['String']['output'];
  createdat: Scalars['Date']['output'];
  isliked: Scalars['Boolean']['output'];
  parentid?: Maybe<Scalars['ID']['output']>;
  postid: Scalars['ID']['output'];
  user: ProfileUser;
  userid: Scalars['ID']['output'];
};

export type CommentInfo = {
  __typename?: 'CommentInfo';
  comments: Scalars['Int']['output'];
  likes: Scalars['Int']['output'];
  reports: Scalars['Int']['output'];
};

export type CommentInfoResponse = {
  __typename?: 'CommentInfoResponse';
  ResponseCode: Scalars['String']['output'];
  affectedRows?: Maybe<CommentInfo>;
  status: Scalars['String']['output'];
};

export type CommentResponse = {
  __typename?: 'CommentResponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  affectedRows?: Maybe<Array<Maybe<Comment>>>;
  counter: Scalars['Int']['output'];
  status: Scalars['String']['output'];
};

export enum CommentType {
  Comment = 'COMMENT'
}

export enum ContentType {
  Audio = 'audio',
  Image = 'image',
  Text = 'text',
  Video = 'video'
}

export type CurrentLiquidity = {
  __typename?: 'CurrentLiquidity';
  ResponseCode: Scalars['String']['output'];
  currentliquidity?: Maybe<Scalars['Decimal']['output']>;
  status: Scalars['String']['output'];
};

export type DailyFreeResponse = {
  __typename?: 'DailyFreeResponse';
  available: Scalars['Int']['output'];
  name: Scalars['String']['output'];
  used: Scalars['Int']['output'];
};

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

export type DefaultResponse = {
  __typename?: 'DefaultResponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  status: Scalars['String']['output'];
};

export type FeedInput = {
  contenttype: Scalars['String']['input'];
  feedid: Scalars['ID']['input'];
  media: Scalars['String']['input'];
  mediadescription?: InputMaybe<Scalars['String']['input']>;
  title: Scalars['String']['input'];
};

export type FollowRelations = {
  __typename?: 'FollowRelations';
  followers?: Maybe<Array<ProfileUser>>;
  following?: Maybe<Array<ProfileUser>>;
};

export type FollowRelationsResponse = {
  __typename?: 'FollowRelationsResponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  affectedRows?: Maybe<FollowRelations>;
  counter: Scalars['Int']['output'];
  status?: Maybe<Scalars['String']['output']>;
};

export type FollowStatusResponse = {
  __typename?: 'FollowStatusResponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  isfollowing?: Maybe<Scalars['Boolean']['output']>;
  status: Scalars['String']['output'];
};

export type GetActionPricesResponse = {
  __typename?: 'GetActionPricesResponse';
  ResponseCode: Scalars['String']['output'];
  affectedRows: ActionPriceResult;
  status: Scalars['String']['output'];
};

export type GetDailyResponse = {
  __typename?: 'GetDailyResponse';
  ResponseCode: Scalars['String']['output'];
  affectedRows?: Maybe<Array<Maybe<DailyFreeResponse>>>;
  status: Scalars['String']['output'];
};

export type HelloResponse = {
  __typename?: 'HelloResponse';
  currentVersion?: Maybe<Scalars['String']['output']>;
  currentuserid?: Maybe<Scalars['ID']['output']>;
  lastMergedPullRequestNumber?: Maybe<Scalars['String']['output']>;
  wikiLink?: Maybe<Scalars['String']['output']>;
};

export enum IgnoreOption {
  No = 'NO',
  Yes = 'YES'
}

export type ListTodaysInteractionsResponse = {
  __typename?: 'ListTodaysInteractionsResponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  affectedRows?: Maybe<TodaysInteractionsData>;
  status: Scalars['String']['output'];
};

export type LogWins = {
  __typename?: 'LogWins';
  action?: Maybe<Scalars['String']['output']>;
  createdat?: Maybe<Scalars['String']['output']>;
  from?: Maybe<Scalars['String']['output']>;
  numbers?: Maybe<Scalars['Decimal']['output']>;
  postid?: Maybe<Scalars['String']['output']>;
  token?: Maybe<Scalars['String']['output']>;
  userid?: Maybe<Scalars['String']['output']>;
};

export type Mutation = {
  __typename?: 'Mutation';
  addChatParticipants: AddChatResponse;
  createChat: AddChatResponse;
  createChatFeed: AddPostResponse;
  createComment: CommentResponse;
  createPost: PostResponse;
  deleteAccount: DefaultResponse;
  deleteChat: DefaultResponse;
  deleteChatMessage: DefaultResponse;
  deletePost: DefaultResponse;
  likeComment: DefaultResponse;
  removeChatParticipants: AddChatResponse;
  reportComment: DefaultResponse;
  resolvePostAction: DefaultResponse;
  resolveTransfer: DefaultResponse;
  sendChatMessage: AddChatmessageResponse;
  toggleBlockUserStatus: DefaultResponse;
  toggleUserFollowStatus: FollowStatusResponse;
  updateBio: DefaultResponse;
  updateChatInformations: AddChatResponse;
  updateEmail: DefaultResponse;
  updatePassword: DefaultResponse;
  updateProfileImage: DefaultResponse;
  updateUsername: DefaultResponse;
};


export type MutationAddChatParticipantsArgs = {
  input: ChatParticipantsInput;
};


export type MutationCreateChatArgs = {
  input: ChatInput;
};


export type MutationCreateChatFeedArgs = {
  input: FeedInput;
};


export type MutationCreateCommentArgs = {
  action: CommentType;
  content: Scalars['String']['input'];
  parentid?: InputMaybe<Scalars['ID']['input']>;
  postid: Scalars['ID']['input'];
};


export type MutationCreatePostArgs = {
  action: PostType;
  input: PostInput;
};


export type MutationDeleteAccountArgs = {
  password: Scalars['String']['input'];
};


export type MutationDeleteChatArgs = {
  id: Scalars['ID']['input'];
};


export type MutationDeleteChatMessageArgs = {
  chatid: Scalars['ID']['input'];
  messid: Scalars['Int']['input'];
};


export type MutationDeletePostArgs = {
  id: Scalars['ID']['input'];
};


export type MutationLikeCommentArgs = {
  commentid: Scalars['ID']['input'];
};


export type MutationRemoveChatParticipantsArgs = {
  input: ChatParticipantsInput;
};


export type MutationReportCommentArgs = {
  commentid: Scalars['ID']['input'];
};


export type MutationResolvePostActionArgs = {
  action: PostActionType;
  postid: Scalars['ID']['input'];
};


export type MutationResolveTransferArgs = {
  numberoftokens: Scalars['Int']['input'];
  recipient: Scalars['ID']['input'];
};


export type MutationSendChatMessageArgs = {
  chatid: Scalars['ID']['input'];
  content: Scalars['String']['input'];
};


export type MutationToggleBlockUserStatusArgs = {
  userid: Scalars['ID']['input'];
};


export type MutationToggleUserFollowStatusArgs = {
  userid: Scalars['ID']['input'];
};


export type MutationUpdateBioArgs = {
  biography: Scalars['String']['input'];
};


export type MutationUpdateChatInformationsArgs = {
  input: UpdateChatInput;
};


export type MutationUpdateEmailArgs = {
  email: Scalars['String']['input'];
  password: Scalars['String']['input'];
};


export type MutationUpdatePasswordArgs = {
  expassword: Scalars['String']['input'];
  password: Scalars['String']['input'];
};


export type MutationUpdateProfileImageArgs = {
  img: Scalars['String']['input'];
};


export type MutationUpdateUsernameArgs = {
  password: Scalars['String']['input'];
  username: Scalars['String']['input'];
};

export type Post = {
  __typename?: 'Post';
  amountcomments: Scalars['Int']['output'];
  amountdislikes: Scalars['Int']['output'];
  amountlikes: Scalars['Int']['output'];
  amounttrending: Scalars['Int']['output'];
  amountviews: Scalars['Int']['output'];
  comments: Array<Comment>;
  contenttype: Scalars['String']['output'];
  cover: Scalars['String']['output'];
  createdat: Scalars['Date']['output'];
  id: Scalars['ID']['output'];
  isdisliked: Scalars['Boolean']['output'];
  isliked: Scalars['Boolean']['output'];
  isreported: Scalars['Boolean']['output'];
  issaved: Scalars['Boolean']['output'];
  isviewed: Scalars['Boolean']['output'];
  media: Scalars['String']['output'];
  mediadescription: Scalars['String']['output'];
  tags: Array<Maybe<Scalars['String']['output']>>;
  title: Scalars['String']['output'];
  user: ProfileUser;
};

export enum PostActionType {
  Dislike = 'DISLIKE',
  Like = 'LIKE',
  Report = 'REPORT',
  Save = 'SAVE',
  Share = 'SHARE',
  View = 'VIEW'
}

export enum PostFilterType {
  Audio = 'AUDIO',
  Followed = 'FOLLOWED',
  Follower = 'FOLLOWER',
  Image = 'IMAGE',
  Text = 'TEXT',
  Video = 'VIDEO'
}

export type PostInfo = {
  __typename?: 'PostInfo';
  comments: Scalars['Int']['output'];
  dislikes: Scalars['Int']['output'];
  likes: Scalars['Int']['output'];
  reports: Scalars['Int']['output'];
  saves: Scalars['Int']['output'];
  shares: Scalars['Int']['output'];
  views: Scalars['Int']['output'];
};

export type PostInfoResponse = {
  __typename?: 'PostInfoResponse';
  ResponseCode: Scalars['String']['output'];
  affectedRows?: Maybe<PostInfo>;
  status: Scalars['String']['output'];
};

export type PostInput = {
  contenttype: ContentType;
  cover?: InputMaybe<Array<Scalars['String']['input']>>;
  media?: InputMaybe<Array<Scalars['String']['input']>>;
  mediadescription?: InputMaybe<Scalars['String']['input']>;
  tags?: InputMaybe<Array<Scalars['String']['input']>>;
  title: Scalars['String']['input'];
};

export type PostListResponse = {
  __typename?: 'PostListResponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  affectedRows?: Maybe<Array<Post>>;
  counter: Scalars['Int']['output'];
  status: Scalars['String']['output'];
};

export type PostResponse = {
  __typename?: 'PostResponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  affectedRows?: Maybe<Post>;
  status: Scalars['String']['output'];
};

export enum PostSortType {
  Comments = 'COMMENTS',
  Dislikes = 'DISLIKES',
  Likes = 'LIKES',
  Newest = 'NEWEST',
  Trending = 'TRENDING',
  Views = 'VIEWS'
}

export enum PostType {
  Post = 'POST'
}

export type Postinfo = {
  __typename?: 'Postinfo';
  contenttype: Scalars['String']['output'];
  media: Scalars['String']['output'];
  mediadescription: Scalars['String']['output'];
  postid: Scalars['ID']['output'];
  title: Scalars['String']['output'];
  userid: Scalars['ID']['output'];
};

export type Profile = {
  __typename?: 'Profile';
  amountblocked?: Maybe<Scalars['Int']['output']>;
  amountfollowed?: Maybe<Scalars['Int']['output']>;
  amountfollower?: Maybe<Scalars['Int']['output']>;
  amountfriends?: Maybe<Scalars['Int']['output']>;
  amountposts?: Maybe<Scalars['Int']['output']>;
  amounttrending?: Maybe<Scalars['Int']['output']>;
  audioposts?: Maybe<Array<ProfilePostMedia>>;
  biography?: Maybe<Scalars['String']['output']>;
  id?: Maybe<Scalars['ID']['output']>;
  imageposts?: Maybe<Array<ProfilePostMedia>>;
  img?: Maybe<Scalars['String']['output']>;
  isfollowed?: Maybe<Scalars['Boolean']['output']>;
  isfollowing?: Maybe<Scalars['Boolean']['output']>;
  slug?: Maybe<Scalars['Int']['output']>;
  status?: Maybe<Scalars['Int']['output']>;
  textposts?: Maybe<Array<ProfilePostMedia>>;
  username?: Maybe<Scalars['String']['output']>;
  videoposts?: Maybe<Array<ProfilePostMedia>>;
};

export type ProfileInfo = {
  __typename?: 'ProfileInfo';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  affectedRows?: Maybe<Profile>;
  status?: Maybe<Scalars['String']['output']>;
};

export type ProfilePostMedia = {
  __typename?: 'ProfilePostMedia';
  contenttype: Scalars['String']['output'];
  createdat?: Maybe<Scalars['String']['output']>;
  id: Scalars['ID']['output'];
  media: Scalars['String']['output'];
  title?: Maybe<Scalars['String']['output']>;
};

export type ProfileUser = {
  __typename?: 'ProfileUser';
  id: Scalars['ID']['output'];
  img?: Maybe<Scalars['String']['output']>;
  isfollowed?: Maybe<Scalars['Boolean']['output']>;
  isfollowing?: Maybe<Scalars['Boolean']['output']>;
  slug?: Maybe<Scalars['Int']['output']>;
  username?: Maybe<Scalars['String']['output']>;
};

export type Query = {
  __typename?: 'Query';
  balance: CurrentLiquidity;
  getActionPrices: GetActionPricesResponse;
  getChat: ChatResponse;
  getCommentInfo: CommentInfoResponse;
  getDailyFreeStatus: GetDailyResponse;
  getPostInfo: PostInfoResponse;
  getProfile: ProfileInfo;
  getReferralInfo: ReferralInfoResponse;
  getUserInfo: UserInfoResponse;
  hello: HelloResponse;
  listBlockedUsers: BlockedUsersResponse;
  listChatMessages: AddChatmessageResponse;
  listChats: ChatResponse;
  listChildComments: CommentResponse;
  listFollowRelations: FollowRelationsResponse;
  listFriends: UserFriendsResponse;
  listPaymentLogs: UserLogWins;
  listPosts: PostListResponse;
  listTags: TagSearchResponse;
  listTodaysInteractions: ListTodaysInteractionsResponse;
  listUsers: UserListResponse;
  listWinLogs: UserLogWins;
  referralList: ReferralListResponse;
  searchTags: TagSearchResponse;
  searchUser: UserListResponse;
};


export type QueryGetChatArgs = {
  chatid: Scalars['ID']['input'];
  messageLimit?: InputMaybe<Scalars['Int']['input']>;
  messageOffset?: InputMaybe<Scalars['Int']['input']>;
};


export type QueryGetCommentInfoArgs = {
  commentId: Scalars['ID']['input'];
};


export type QueryGetPostInfoArgs = {
  postid: Scalars['ID']['input'];
};


export type QueryGetProfileArgs = {
  postLimit?: InputMaybe<Scalars['Int']['input']>;
  userid?: InputMaybe<Scalars['ID']['input']>;
};


export type QueryListBlockedUsersArgs = {
  limit?: InputMaybe<Scalars['Int']['input']>;
  offset?: InputMaybe<Scalars['Int']['input']>;
};


export type QueryListChatMessagesArgs = {
  chatid: Scalars['ID']['input'];
  limit?: InputMaybe<Scalars['Int']['input']>;
  offset?: InputMaybe<Scalars['Int']['input']>;
};


export type QueryListChatsArgs = {
  from?: InputMaybe<Scalars['Date']['input']>;
  limit?: InputMaybe<Scalars['Int']['input']>;
  messageLimit?: InputMaybe<Scalars['Int']['input']>;
  messageOffset?: InputMaybe<Scalars['Int']['input']>;
  offset?: InputMaybe<Scalars['Int']['input']>;
  sortBy?: InputMaybe<Scalars['String']['input']>;
  to?: InputMaybe<Scalars['Date']['input']>;
};


export type QueryListChildCommentsArgs = {
  limit?: InputMaybe<Scalars['Int']['input']>;
  offset?: InputMaybe<Scalars['Int']['input']>;
  parent: Scalars['ID']['input'];
};


export type QueryListFollowRelationsArgs = {
  limit?: InputMaybe<Scalars['Int']['input']>;
  offset?: InputMaybe<Scalars['Int']['input']>;
  userid?: InputMaybe<Scalars['ID']['input']>;
};


export type QueryListFriendsArgs = {
  limit?: InputMaybe<Scalars['Int']['input']>;
  offset?: InputMaybe<Scalars['Int']['input']>;
};


export type QueryListPaymentLogsArgs = {
  day: DayFilterType;
  limit?: InputMaybe<Scalars['Int']['input']>;
  offset?: InputMaybe<Scalars['Int']['input']>;
};


export type QueryListPostsArgs = {
  IgnorList?: InputMaybe<IgnoreOption>;
  commentLimit?: InputMaybe<Scalars['Int']['input']>;
  commentOffset?: InputMaybe<Scalars['Int']['input']>;
  filterBy?: InputMaybe<Array<PostFilterType>>;
  from?: InputMaybe<Scalars['Date']['input']>;
  limit?: InputMaybe<Scalars['Int']['input']>;
  offset?: InputMaybe<Scalars['Int']['input']>;
  postid?: InputMaybe<Scalars['ID']['input']>;
  sortBy?: InputMaybe<PostSortType>;
  tag?: InputMaybe<Scalars['String']['input']>;
  title?: InputMaybe<Scalars['String']['input']>;
  to?: InputMaybe<Scalars['Date']['input']>;
  userid?: InputMaybe<Scalars['ID']['input']>;
};


export type QueryListTagsArgs = {
  limit?: InputMaybe<Scalars['Int']['input']>;
  offset?: InputMaybe<Scalars['Int']['input']>;
};


export type QueryListUsersArgs = {
  limit?: InputMaybe<Scalars['Int']['input']>;
  offset?: InputMaybe<Scalars['Int']['input']>;
};


export type QueryListWinLogsArgs = {
  day: DayFilterType;
  limit?: InputMaybe<Scalars['Int']['input']>;
  offset?: InputMaybe<Scalars['Int']['input']>;
};


export type QueryReferralListArgs = {
  limit?: InputMaybe<Scalars['Int']['input']>;
  offset?: InputMaybe<Scalars['Int']['input']>;
};


export type QuerySearchTagsArgs = {
  limit?: InputMaybe<Scalars['Int']['input']>;
  offset?: InputMaybe<Scalars['Int']['input']>;
  tagName: Scalars['String']['input'];
};


export type QuerySearchUserArgs = {
  limit?: InputMaybe<Scalars['Int']['input']>;
  offset?: InputMaybe<Scalars['Int']['input']>;
  userid?: InputMaybe<Scalars['ID']['input']>;
  username?: InputMaybe<Scalars['String']['input']>;
};

export type ReferralInfoResponse = {
  __typename?: 'ReferralInfoResponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  referralLink?: Maybe<Scalars['String']['output']>;
  referralUuid?: Maybe<Scalars['ID']['output']>;
  status?: Maybe<Scalars['String']['output']>;
};

export type ReferralListResponse = {
  __typename?: 'ReferralListResponse';
  ResponseCode: Scalars['String']['output'];
  affectedRows: ReferralUsers;
  counter: Scalars['Int']['output'];
  status: Scalars['String']['output'];
};

export type ReferralUsers = {
  __typename?: 'ReferralUsers';
  iInvited: Array<ProfileUser>;
  invitedBy?: Maybe<ProfileUser>;
};

export type Subscription = {
  __typename?: 'Subscription';
  getBtcTokenPrice?: Maybe<Scalars['String']['output']>;
  getChatMessages: AddChatmessageResponse;
  numberIncremented?: Maybe<Scalars['Int']['output']>;
  setChatMessages: AddChatmessageResponse;
};


export type SubscriptionGetChatMessagesArgs = {
  chatid: Scalars['ID']['input'];
};


export type SubscriptionSetChatMessagesArgs = {
  chatid: Scalars['ID']['input'];
  content: Scalars['String']['input'];
};

export type Tag = {
  __typename?: 'Tag';
  name: Scalars['String']['output'];
};

export type TagSearchResponse = {
  __typename?: 'TagSearchResponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  affectedRows?: Maybe<Array<Maybe<Tag>>>;
  counter: Scalars['Int']['output'];
  status: Scalars['String']['output'];
};

export type TodaysInteractionsData = {
  __typename?: 'TodaysInteractionsData';
  totalDetails?: Maybe<TodaysInteractionsDetailsData>;
  totalInteractions?: Maybe<Scalars['Decimal']['output']>;
  totalScore?: Maybe<Scalars['Decimal']['output']>;
};

export type TodaysInteractionsDetailsData = {
  __typename?: 'TodaysInteractionsDetailsData';
  comments?: Maybe<Scalars['Decimal']['output']>;
  commentsScore?: Maybe<Scalars['Decimal']['output']>;
  dislikes?: Maybe<Scalars['Decimal']['output']>;
  dislikesScore?: Maybe<Scalars['Decimal']['output']>;
  likes?: Maybe<Scalars['Decimal']['output']>;
  likesScore?: Maybe<Scalars['Decimal']['output']>;
  views?: Maybe<Scalars['Decimal']['output']>;
  viewsScore?: Maybe<Scalars['Decimal']['output']>;
};

export type UpdateChatInput = {
  chatid: Scalars['ID']['input'];
  image?: InputMaybe<Scalars['String']['input']>;
  name?: InputMaybe<Scalars['String']['input']>;
};

export type User = {
  __typename?: 'User';
  biography?: Maybe<Scalars['String']['output']>;
  createdat?: Maybe<Scalars['Date']['output']>;
  id?: Maybe<Scalars['ID']['output']>;
  img?: Maybe<Scalars['String']['output']>;
  slug?: Maybe<Scalars['Int']['output']>;
  status?: Maybe<Scalars['Int']['output']>;
  updatedat?: Maybe<Scalars['Date']['output']>;
  username?: Maybe<Scalars['String']['output']>;
};

export type UserFriendsResponse = {
  __typename?: 'UserFriendsResponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  affectedRows?: Maybe<Array<Maybe<Userinfo>>>;
  counter: Scalars['Int']['output'];
  status: Scalars['String']['output'];
};

export type UserInfo = {
  __typename?: 'UserInfo';
  amountblocked: Scalars['Int']['output'];
  amountfollowed: Scalars['Int']['output'];
  amountfollower: Scalars['Int']['output'];
  amountfriends: Scalars['Int']['output'];
  amountposts: Scalars['Int']['output'];
  invited: Scalars['ID']['output'];
  liquidity: Scalars['Decimal']['output'];
  updatedat?: Maybe<Scalars['Date']['output']>;
  userid: Scalars['ID']['output'];
};

export type UserInfoResponse = {
  __typename?: 'UserInfoResponse';
  ResponseCode: Scalars['String']['output'];
  affectedRows?: Maybe<UserInfo>;
  status: Scalars['String']['output'];
};

export type UserListResponse = {
  __typename?: 'UserListResponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  affectedRows?: Maybe<Array<Maybe<User>>>;
  counter: Scalars['Int']['output'];
  status: Scalars['String']['output'];
};

export type UserLogWins = {
  __typename?: 'UserLogWins';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  affectedRows?: Maybe<Array<Maybe<LogWins>>>;
  counter: Scalars['Int']['output'];
  status?: Maybe<Scalars['String']['output']>;
};

export type Userinfo = {
  __typename?: 'Userinfo';
  biography?: Maybe<Scalars['String']['output']>;
  img?: Maybe<Scalars['String']['output']>;
  slug?: Maybe<Scalars['Int']['output']>;
  updatedat: Scalars['Date']['output'];
  userid?: Maybe<Scalars['ID']['output']>;
  username?: Maybe<Scalars['String']['output']>;
};

export type Userinforesponse = {
  __typename?: 'Userinforesponse';
  ResponseCode?: Maybe<Scalars['String']['output']>;
  affectedRows?: Maybe<Userinfo>;
  status: Scalars['String']['output'];
};

export type WithIndex<TObject> = TObject & Record<string, any>;
export type ResolversObject<TObject> = WithIndex<TObject>;

export type ResolverTypeWrapper<T> = Promise<T> | T;


export type ResolverWithResolve<TResult, TParent, TContext, TArgs> = {
  resolve: ResolverFn<TResult, TParent, TContext, TArgs>;
};
export type Resolver<TResult, TParent = {}, TContext = {}, TArgs = {}> = ResolverFn<TResult, TParent, TContext, TArgs> | ResolverWithResolve<TResult, TParent, TContext, TArgs>;

export type ResolverFn<TResult, TParent, TContext, TArgs> = (
  parent: TParent,
  args: TArgs,
  context: TContext,
  info: GraphQLResolveInfo
) => Promise<TResult> | TResult;

export type SubscriptionSubscribeFn<TResult, TParent, TContext, TArgs> = (
  parent: TParent,
  args: TArgs,
  context: TContext,
  info: GraphQLResolveInfo
) => AsyncIterable<TResult> | Promise<AsyncIterable<TResult>>;

export type SubscriptionResolveFn<TResult, TParent, TContext, TArgs> = (
  parent: TParent,
  args: TArgs,
  context: TContext,
  info: GraphQLResolveInfo
) => TResult | Promise<TResult>;

export interface SubscriptionSubscriberObject<TResult, TKey extends string, TParent, TContext, TArgs> {
  subscribe: SubscriptionSubscribeFn<{ [key in TKey]: TResult }, TParent, TContext, TArgs>;
  resolve?: SubscriptionResolveFn<TResult, { [key in TKey]: TResult }, TContext, TArgs>;
}

export interface SubscriptionResolverObject<TResult, TParent, TContext, TArgs> {
  subscribe: SubscriptionSubscribeFn<any, TParent, TContext, TArgs>;
  resolve: SubscriptionResolveFn<TResult, any, TContext, TArgs>;
}

export type SubscriptionObject<TResult, TKey extends string, TParent, TContext, TArgs> =
  | SubscriptionSubscriberObject<TResult, TKey, TParent, TContext, TArgs>
  | SubscriptionResolverObject<TResult, TParent, TContext, TArgs>;

export type SubscriptionResolver<TResult, TKey extends string, TParent = {}, TContext = {}, TArgs = {}> =
  | ((...args: any[]) => SubscriptionObject<TResult, TKey, TParent, TContext, TArgs>)
  | SubscriptionObject<TResult, TKey, TParent, TContext, TArgs>;

export type TypeResolveFn<TTypes, TParent = {}, TContext = {}> = (
  parent: TParent,
  context: TContext,
  info: GraphQLResolveInfo
) => Maybe<TTypes> | Promise<Maybe<TTypes>>;

export type IsTypeOfResolverFn<T = {}, TContext = {}> = (obj: T, context: TContext, info: GraphQLResolveInfo) => boolean | Promise<boolean>;

export type NextResolverFn<T> = () => Promise<T>;

export type DirectiveResolverFn<TResult = {}, TParent = {}, TContext = {}, TArgs = {}> = (
  next: NextResolverFn<TResult>,
  parent: TParent,
  args: TArgs,
  context: TContext,
  info: GraphQLResolveInfo
) => TResult | Promise<TResult>;



/** Mapping between all available schema types and the resolvers types */
export type ResolversTypes = ResolversObject<{
  ActionPriceResult: ResolverTypeWrapper<ActionPriceResult>;
  AddChatResponse: ResolverTypeWrapper<AddChatResponse>;
  AddChatmessageResponse: ResolverTypeWrapper<AddChatmessageResponse>;
  AddPostResponse: ResolverTypeWrapper<AddPostResponse>;
  BlockedUser: ResolverTypeWrapper<BlockedUser>;
  BlockedUsers: ResolverTypeWrapper<BlockedUsers>;
  BlockedUsersResponse: ResolverTypeWrapper<BlockedUsersResponse>;
  Boolean: ResolverTypeWrapper<Scalars['Boolean']['output']>;
  BtcTokenPriceResponse: ResolverTypeWrapper<BtcTokenPriceResponse>;
  Chat: ResolverTypeWrapper<Chat>;
  ChatInput: ChatInput;
  ChatMessage: ResolverTypeWrapper<ChatMessage>;
  ChatMessageInfo: ResolverTypeWrapper<ChatMessageInfo>;
  ChatParticipant: ResolverTypeWrapper<ChatParticipant>;
  ChatParticipantsInput: ChatParticipantsInput;
  ChatResponse: ResolverTypeWrapper<ChatResponse>;
  Chatinfo: ResolverTypeWrapper<Chatinfo>;
  Comment: ResolverTypeWrapper<Comment>;
  CommentInfo: ResolverTypeWrapper<CommentInfo>;
  CommentInfoResponse: ResolverTypeWrapper<CommentInfoResponse>;
  CommentResponse: ResolverTypeWrapper<CommentResponse>;
  CommentType: CommentType;
  ContentType: ContentType;
  CurrentLiquidity: ResolverTypeWrapper<CurrentLiquidity>;
  DailyFreeResponse: ResolverTypeWrapper<DailyFreeResponse>;
  Date: ResolverTypeWrapper<Scalars['Date']['output']>;
  DayFilterType: DayFilterType;
  Decimal: ResolverTypeWrapper<Scalars['Decimal']['output']>;
  DefaultResponse: ResolverTypeWrapper<DefaultResponse>;
  FeedInput: FeedInput;
  Float: ResolverTypeWrapper<Scalars['Float']['output']>;
  FollowRelations: ResolverTypeWrapper<FollowRelations>;
  FollowRelationsResponse: ResolverTypeWrapper<FollowRelationsResponse>;
  FollowStatusResponse: ResolverTypeWrapper<FollowStatusResponse>;
  GetActionPricesResponse: ResolverTypeWrapper<GetActionPricesResponse>;
  GetDailyResponse: ResolverTypeWrapper<GetDailyResponse>;
  HelloResponse: ResolverTypeWrapper<HelloResponse>;
  ID: ResolverTypeWrapper<Scalars['ID']['output']>;
  IgnoreOption: IgnoreOption;
  Int: ResolverTypeWrapper<Scalars['Int']['output']>;
  ListTodaysInteractionsResponse: ResolverTypeWrapper<ListTodaysInteractionsResponse>;
  LogWins: ResolverTypeWrapper<LogWins>;
  Mutation: ResolverTypeWrapper<{}>;
  Post: ResolverTypeWrapper<Post>;
  PostActionType: PostActionType;
  PostFilterType: PostFilterType;
  PostInfo: ResolverTypeWrapper<PostInfo>;
  PostInfoResponse: ResolverTypeWrapper<PostInfoResponse>;
  PostInput: PostInput;
  PostListResponse: ResolverTypeWrapper<PostListResponse>;
  PostResponse: ResolverTypeWrapper<PostResponse>;
  PostSortType: PostSortType;
  PostType: PostType;
  Postinfo: ResolverTypeWrapper<Postinfo>;
  Profile: ResolverTypeWrapper<Profile>;
  ProfileInfo: ResolverTypeWrapper<ProfileInfo>;
  ProfilePostMedia: ResolverTypeWrapper<ProfilePostMedia>;
  ProfileUser: ResolverTypeWrapper<ProfileUser>;
  Query: ResolverTypeWrapper<{}>;
  ReferralInfoResponse: ResolverTypeWrapper<ReferralInfoResponse>;
  ReferralListResponse: ResolverTypeWrapper<ReferralListResponse>;
  ReferralUsers: ResolverTypeWrapper<ReferralUsers>;
  String: ResolverTypeWrapper<Scalars['String']['output']>;
  Subscription: ResolverTypeWrapper<{}>;
  Tag: ResolverTypeWrapper<Tag>;
  TagSearchResponse: ResolverTypeWrapper<TagSearchResponse>;
  TodaysInteractionsData: ResolverTypeWrapper<TodaysInteractionsData>;
  TodaysInteractionsDetailsData: ResolverTypeWrapper<TodaysInteractionsDetailsData>;
  UpdateChatInput: UpdateChatInput;
  User: ResolverTypeWrapper<User>;
  UserFriendsResponse: ResolverTypeWrapper<UserFriendsResponse>;
  UserInfo: ResolverTypeWrapper<UserInfo>;
  UserInfoResponse: ResolverTypeWrapper<UserInfoResponse>;
  UserListResponse: ResolverTypeWrapper<UserListResponse>;
  UserLogWins: ResolverTypeWrapper<UserLogWins>;
  Userinfo: ResolverTypeWrapper<Userinfo>;
  Userinforesponse: ResolverTypeWrapper<Userinforesponse>;
}>;

/** Mapping between all available schema types and the resolvers parents */
export type ResolversParentTypes = ResolversObject<{
  ActionPriceResult: ActionPriceResult;
  AddChatResponse: AddChatResponse;
  AddChatmessageResponse: AddChatmessageResponse;
  AddPostResponse: AddPostResponse;
  BlockedUser: BlockedUser;
  BlockedUsers: BlockedUsers;
  BlockedUsersResponse: BlockedUsersResponse;
  Boolean: Scalars['Boolean']['output'];
  BtcTokenPriceResponse: BtcTokenPriceResponse;
  Chat: Chat;
  ChatInput: ChatInput;
  ChatMessage: ChatMessage;
  ChatMessageInfo: ChatMessageInfo;
  ChatParticipant: ChatParticipant;
  ChatParticipantsInput: ChatParticipantsInput;
  ChatResponse: ChatResponse;
  Chatinfo: Chatinfo;
  Comment: Comment;
  CommentInfo: CommentInfo;
  CommentInfoResponse: CommentInfoResponse;
  CommentResponse: CommentResponse;
  CurrentLiquidity: CurrentLiquidity;
  DailyFreeResponse: DailyFreeResponse;
  Date: Scalars['Date']['output'];
  Decimal: Scalars['Decimal']['output'];
  DefaultResponse: DefaultResponse;
  FeedInput: FeedInput;
  Float: Scalars['Float']['output'];
  FollowRelations: FollowRelations;
  FollowRelationsResponse: FollowRelationsResponse;
  FollowStatusResponse: FollowStatusResponse;
  GetActionPricesResponse: GetActionPricesResponse;
  GetDailyResponse: GetDailyResponse;
  HelloResponse: HelloResponse;
  ID: Scalars['ID']['output'];
  Int: Scalars['Int']['output'];
  ListTodaysInteractionsResponse: ListTodaysInteractionsResponse;
  LogWins: LogWins;
  Mutation: {};
  Post: Post;
  PostInfo: PostInfo;
  PostInfoResponse: PostInfoResponse;
  PostInput: PostInput;
  PostListResponse: PostListResponse;
  PostResponse: PostResponse;
  Postinfo: Postinfo;
  Profile: Profile;
  ProfileInfo: ProfileInfo;
  ProfilePostMedia: ProfilePostMedia;
  ProfileUser: ProfileUser;
  Query: {};
  ReferralInfoResponse: ReferralInfoResponse;
  ReferralListResponse: ReferralListResponse;
  ReferralUsers: ReferralUsers;
  String: Scalars['String']['output'];
  Subscription: {};
  Tag: Tag;
  TagSearchResponse: TagSearchResponse;
  TodaysInteractionsData: TodaysInteractionsData;
  TodaysInteractionsDetailsData: TodaysInteractionsDetailsData;
  UpdateChatInput: UpdateChatInput;
  User: User;
  UserFriendsResponse: UserFriendsResponse;
  UserInfo: UserInfo;
  UserInfoResponse: UserInfoResponse;
  UserListResponse: UserListResponse;
  UserLogWins: UserLogWins;
  Userinfo: Userinfo;
  Userinforesponse: Userinforesponse;
}>;

export type ActionPriceResultResolvers<ContextType = any, ParentType extends ResolversParentTypes['ActionPriceResult'] = ResolversParentTypes['ActionPriceResult']> = ResolversObject<{
  commentPrice?: Resolver<ResolversTypes['Float'], ParentType, ContextType>;
  dislikePrice?: Resolver<ResolversTypes['Float'], ParentType, ContextType>;
  likePrice?: Resolver<ResolversTypes['Float'], ParentType, ContextType>;
  postPrice?: Resolver<ResolversTypes['Float'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type AddChatResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['AddChatResponse'] = ResolversParentTypes['AddChatResponse']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<ResolversTypes['Chatinfo']>, ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type AddChatmessageResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['AddChatmessageResponse'] = ResolversParentTypes['AddChatmessageResponse']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<Array<Maybe<ResolversTypes['ChatMessageInfo']>>>, ParentType, ContextType>;
  counter?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type AddPostResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['AddPostResponse'] = ResolversParentTypes['AddPostResponse']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<ResolversTypes['Postinfo']>, ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type BlockedUserResolvers<ContextType = any, ParentType extends ResolversParentTypes['BlockedUser'] = ResolversParentTypes['BlockedUser']> = ResolversObject<{
  img?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  slug?: Resolver<Maybe<ResolversTypes['Int']>, ParentType, ContextType>;
  userid?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  username?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type BlockedUsersResolvers<ContextType = any, ParentType extends ResolversParentTypes['BlockedUsers'] = ResolversParentTypes['BlockedUsers']> = ResolversObject<{
  blockedBy?: Resolver<Maybe<Array<ResolversTypes['BlockedUser']>>, ParentType, ContextType>;
  iBlocked?: Resolver<Maybe<Array<ResolversTypes['BlockedUser']>>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type BlockedUsersResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['BlockedUsersResponse'] = ResolversParentTypes['BlockedUsersResponse']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<ResolversTypes['BlockedUsers']>, ParentType, ContextType>;
  counter?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  status?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type BtcTokenPriceResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['BtcTokenPriceResponse'] = ResolversParentTypes['BtcTokenPriceResponse']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  btcTokenPrice?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type ChatResolvers<ContextType = any, ParentType extends ResolversParentTypes['Chat'] = ResolversParentTypes['Chat']> = ResolversObject<{
  chatmessages?: Resolver<Array<ResolversTypes['ChatMessage']>, ParentType, ContextType>;
  chatparticipants?: Resolver<Array<ResolversTypes['ChatParticipant']>, ParentType, ContextType>;
  createdat?: Resolver<Maybe<ResolversTypes['Date']>, ParentType, ContextType>;
  id?: Resolver<ResolversTypes['ID'], ParentType, ContextType>;
  image?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  name?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  updatedat?: Resolver<Maybe<ResolversTypes['Date']>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type ChatMessageResolvers<ContextType = any, ParentType extends ResolversParentTypes['ChatMessage'] = ResolversParentTypes['ChatMessage']> = ResolversObject<{
  chatid?: Resolver<ResolversTypes['ID'], ParentType, ContextType>;
  content?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  createdat?: Resolver<ResolversTypes['Date'], ParentType, ContextType>;
  id?: Resolver<ResolversTypes['ID'], ParentType, ContextType>;
  senderid?: Resolver<ResolversTypes['ID'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type ChatMessageInfoResolvers<ContextType = any, ParentType extends ResolversParentTypes['ChatMessageInfo'] = ResolversParentTypes['ChatMessageInfo']> = ResolversObject<{
  chatid?: Resolver<Maybe<ResolversTypes['ID']>, ParentType, ContextType>;
  content?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  createdat?: Resolver<Maybe<ResolversTypes['Date']>, ParentType, ContextType>;
  messid?: Resolver<Maybe<ResolversTypes['ID']>, ParentType, ContextType>;
  userid?: Resolver<Maybe<ResolversTypes['ID']>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type ChatParticipantResolvers<ContextType = any, ParentType extends ResolversParentTypes['ChatParticipant'] = ResolversParentTypes['ChatParticipant']> = ResolversObject<{
  hasaccess?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  img?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  slug?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  userid?: Resolver<ResolversTypes['ID'], ParentType, ContextType>;
  username?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type ChatResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['ChatResponse'] = ResolversParentTypes['ChatResponse']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<Array<ResolversTypes['Chat']>>, ParentType, ContextType>;
  counter?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type ChatinfoResolvers<ContextType = any, ParentType extends ResolversParentTypes['Chatinfo'] = ResolversParentTypes['Chatinfo']> = ResolversObject<{
  chatid?: Resolver<Maybe<ResolversTypes['ID']>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type CommentResolvers<ContextType = any, ParentType extends ResolversParentTypes['Comment'] = ResolversParentTypes['Comment']> = ResolversObject<{
  amountlikes?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  amountreplies?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  commentid?: Resolver<ResolversTypes['ID'], ParentType, ContextType>;
  content?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  createdat?: Resolver<ResolversTypes['Date'], ParentType, ContextType>;
  isliked?: Resolver<ResolversTypes['Boolean'], ParentType, ContextType>;
  parentid?: Resolver<Maybe<ResolversTypes['ID']>, ParentType, ContextType>;
  postid?: Resolver<ResolversTypes['ID'], ParentType, ContextType>;
  user?: Resolver<ResolversTypes['ProfileUser'], ParentType, ContextType>;
  userid?: Resolver<ResolversTypes['ID'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type CommentInfoResolvers<ContextType = any, ParentType extends ResolversParentTypes['CommentInfo'] = ResolversParentTypes['CommentInfo']> = ResolversObject<{
  comments?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  likes?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  reports?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type CommentInfoResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['CommentInfoResponse'] = ResolversParentTypes['CommentInfoResponse']> = ResolversObject<{
  ResponseCode?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<ResolversTypes['CommentInfo']>, ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type CommentResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['CommentResponse'] = ResolversParentTypes['CommentResponse']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<Array<Maybe<ResolversTypes['Comment']>>>, ParentType, ContextType>;
  counter?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type CurrentLiquidityResolvers<ContextType = any, ParentType extends ResolversParentTypes['CurrentLiquidity'] = ResolversParentTypes['CurrentLiquidity']> = ResolversObject<{
  ResponseCode?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  currentliquidity?: Resolver<Maybe<ResolversTypes['Decimal']>, ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type DailyFreeResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['DailyFreeResponse'] = ResolversParentTypes['DailyFreeResponse']> = ResolversObject<{
  available?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  name?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  used?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export interface DateScalarConfig extends GraphQLScalarTypeConfig<ResolversTypes['Date'], any> {
  name: 'Date';
}

export interface DecimalScalarConfig extends GraphQLScalarTypeConfig<ResolversTypes['Decimal'], any> {
  name: 'Decimal';
}

export type DefaultResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['DefaultResponse'] = ResolversParentTypes['DefaultResponse']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type FollowRelationsResolvers<ContextType = any, ParentType extends ResolversParentTypes['FollowRelations'] = ResolversParentTypes['FollowRelations']> = ResolversObject<{
  followers?: Resolver<Maybe<Array<ResolversTypes['ProfileUser']>>, ParentType, ContextType>;
  following?: Resolver<Maybe<Array<ResolversTypes['ProfileUser']>>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type FollowRelationsResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['FollowRelationsResponse'] = ResolversParentTypes['FollowRelationsResponse']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<ResolversTypes['FollowRelations']>, ParentType, ContextType>;
  counter?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  status?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type FollowStatusResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['FollowStatusResponse'] = ResolversParentTypes['FollowStatusResponse']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  isfollowing?: Resolver<Maybe<ResolversTypes['Boolean']>, ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type GetActionPricesResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['GetActionPricesResponse'] = ResolversParentTypes['GetActionPricesResponse']> = ResolversObject<{
  ResponseCode?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  affectedRows?: Resolver<ResolversTypes['ActionPriceResult'], ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type GetDailyResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['GetDailyResponse'] = ResolversParentTypes['GetDailyResponse']> = ResolversObject<{
  ResponseCode?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<Array<Maybe<ResolversTypes['DailyFreeResponse']>>>, ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type HelloResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['HelloResponse'] = ResolversParentTypes['HelloResponse']> = ResolversObject<{
  currentVersion?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  currentuserid?: Resolver<Maybe<ResolversTypes['ID']>, ParentType, ContextType>;
  lastMergedPullRequestNumber?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  wikiLink?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type ListTodaysInteractionsResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['ListTodaysInteractionsResponse'] = ResolversParentTypes['ListTodaysInteractionsResponse']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<ResolversTypes['TodaysInteractionsData']>, ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type LogWinsResolvers<ContextType = any, ParentType extends ResolversParentTypes['LogWins'] = ResolversParentTypes['LogWins']> = ResolversObject<{
  action?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  createdat?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  from?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  numbers?: Resolver<Maybe<ResolversTypes['Decimal']>, ParentType, ContextType>;
  postid?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  token?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  userid?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type MutationResolvers<ContextType = any, ParentType extends ResolversParentTypes['Mutation'] = ResolversParentTypes['Mutation']> = ResolversObject<{
  addChatParticipants?: Resolver<ResolversTypes['AddChatResponse'], ParentType, ContextType, RequireFields<MutationAddChatParticipantsArgs, 'input'>>;
  createChat?: Resolver<ResolversTypes['AddChatResponse'], ParentType, ContextType, RequireFields<MutationCreateChatArgs, 'input'>>;
  createChatFeed?: Resolver<ResolversTypes['AddPostResponse'], ParentType, ContextType, RequireFields<MutationCreateChatFeedArgs, 'input'>>;
  createComment?: Resolver<ResolversTypes['CommentResponse'], ParentType, ContextType, RequireFields<MutationCreateCommentArgs, 'action' | 'content' | 'postid'>>;
  createPost?: Resolver<ResolversTypes['PostResponse'], ParentType, ContextType, RequireFields<MutationCreatePostArgs, 'action' | 'input'>>;
  deleteAccount?: Resolver<ResolversTypes['DefaultResponse'], ParentType, ContextType, RequireFields<MutationDeleteAccountArgs, 'password'>>;
  deleteChat?: Resolver<ResolversTypes['DefaultResponse'], ParentType, ContextType, RequireFields<MutationDeleteChatArgs, 'id'>>;
  deleteChatMessage?: Resolver<ResolversTypes['DefaultResponse'], ParentType, ContextType, RequireFields<MutationDeleteChatMessageArgs, 'chatid' | 'messid'>>;
  deletePost?: Resolver<ResolversTypes['DefaultResponse'], ParentType, ContextType, RequireFields<MutationDeletePostArgs, 'id'>>;
  likeComment?: Resolver<ResolversTypes['DefaultResponse'], ParentType, ContextType, RequireFields<MutationLikeCommentArgs, 'commentid'>>;
  removeChatParticipants?: Resolver<ResolversTypes['AddChatResponse'], ParentType, ContextType, RequireFields<MutationRemoveChatParticipantsArgs, 'input'>>;
  reportComment?: Resolver<ResolversTypes['DefaultResponse'], ParentType, ContextType, RequireFields<MutationReportCommentArgs, 'commentid'>>;
  resolvePostAction?: Resolver<ResolversTypes['DefaultResponse'], ParentType, ContextType, RequireFields<MutationResolvePostActionArgs, 'action' | 'postid'>>;
  resolveTransfer?: Resolver<ResolversTypes['DefaultResponse'], ParentType, ContextType, RequireFields<MutationResolveTransferArgs, 'numberoftokens' | 'recipient'>>;
  sendChatMessage?: Resolver<ResolversTypes['AddChatmessageResponse'], ParentType, ContextType, RequireFields<MutationSendChatMessageArgs, 'chatid' | 'content'>>;
  toggleBlockUserStatus?: Resolver<ResolversTypes['DefaultResponse'], ParentType, ContextType, RequireFields<MutationToggleBlockUserStatusArgs, 'userid'>>;
  toggleUserFollowStatus?: Resolver<ResolversTypes['FollowStatusResponse'], ParentType, ContextType, RequireFields<MutationToggleUserFollowStatusArgs, 'userid'>>;
  updateBio?: Resolver<ResolversTypes['DefaultResponse'], ParentType, ContextType, RequireFields<MutationUpdateBioArgs, 'biography'>>;
  updateChatInformations?: Resolver<ResolversTypes['AddChatResponse'], ParentType, ContextType, RequireFields<MutationUpdateChatInformationsArgs, 'input'>>;
  updateEmail?: Resolver<ResolversTypes['DefaultResponse'], ParentType, ContextType, RequireFields<MutationUpdateEmailArgs, 'email' | 'password'>>;
  updatePassword?: Resolver<ResolversTypes['DefaultResponse'], ParentType, ContextType, RequireFields<MutationUpdatePasswordArgs, 'expassword' | 'password'>>;
  updateProfileImage?: Resolver<ResolversTypes['DefaultResponse'], ParentType, ContextType, RequireFields<MutationUpdateProfileImageArgs, 'img'>>;
  updateUsername?: Resolver<ResolversTypes['DefaultResponse'], ParentType, ContextType, RequireFields<MutationUpdateUsernameArgs, 'password' | 'username'>>;
}>;

export type PostResolvers<ContextType = any, ParentType extends ResolversParentTypes['Post'] = ResolversParentTypes['Post']> = ResolversObject<{
  amountcomments?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  amountdislikes?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  amountlikes?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  amounttrending?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  amountviews?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  comments?: Resolver<Array<ResolversTypes['Comment']>, ParentType, ContextType>;
  contenttype?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  cover?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  createdat?: Resolver<ResolversTypes['Date'], ParentType, ContextType>;
  id?: Resolver<ResolversTypes['ID'], ParentType, ContextType>;
  isdisliked?: Resolver<ResolversTypes['Boolean'], ParentType, ContextType>;
  isliked?: Resolver<ResolversTypes['Boolean'], ParentType, ContextType>;
  isreported?: Resolver<ResolversTypes['Boolean'], ParentType, ContextType>;
  issaved?: Resolver<ResolversTypes['Boolean'], ParentType, ContextType>;
  isviewed?: Resolver<ResolversTypes['Boolean'], ParentType, ContextType>;
  media?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  mediadescription?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  tags?: Resolver<Array<Maybe<ResolversTypes['String']>>, ParentType, ContextType>;
  title?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  user?: Resolver<ResolversTypes['ProfileUser'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type PostInfoResolvers<ContextType = any, ParentType extends ResolversParentTypes['PostInfo'] = ResolversParentTypes['PostInfo']> = ResolversObject<{
  comments?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  dislikes?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  likes?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  reports?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  saves?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  shares?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  views?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type PostInfoResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['PostInfoResponse'] = ResolversParentTypes['PostInfoResponse']> = ResolversObject<{
  ResponseCode?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<ResolversTypes['PostInfo']>, ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type PostListResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['PostListResponse'] = ResolversParentTypes['PostListResponse']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<Array<ResolversTypes['Post']>>, ParentType, ContextType>;
  counter?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type PostResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['PostResponse'] = ResolversParentTypes['PostResponse']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<ResolversTypes['Post']>, ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type PostinfoResolvers<ContextType = any, ParentType extends ResolversParentTypes['Postinfo'] = ResolversParentTypes['Postinfo']> = ResolversObject<{
  contenttype?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  media?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  mediadescription?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  postid?: Resolver<ResolversTypes['ID'], ParentType, ContextType>;
  title?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  userid?: Resolver<ResolversTypes['ID'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type ProfileResolvers<ContextType = any, ParentType extends ResolversParentTypes['Profile'] = ResolversParentTypes['Profile']> = ResolversObject<{
  amountblocked?: Resolver<Maybe<ResolversTypes['Int']>, ParentType, ContextType>;
  amountfollowed?: Resolver<Maybe<ResolversTypes['Int']>, ParentType, ContextType>;
  amountfollower?: Resolver<Maybe<ResolversTypes['Int']>, ParentType, ContextType>;
  amountfriends?: Resolver<Maybe<ResolversTypes['Int']>, ParentType, ContextType>;
  amountposts?: Resolver<Maybe<ResolversTypes['Int']>, ParentType, ContextType>;
  amounttrending?: Resolver<Maybe<ResolversTypes['Int']>, ParentType, ContextType>;
  audioposts?: Resolver<Maybe<Array<ResolversTypes['ProfilePostMedia']>>, ParentType, ContextType>;
  biography?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  id?: Resolver<Maybe<ResolversTypes['ID']>, ParentType, ContextType>;
  imageposts?: Resolver<Maybe<Array<ResolversTypes['ProfilePostMedia']>>, ParentType, ContextType>;
  img?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  isfollowed?: Resolver<Maybe<ResolversTypes['Boolean']>, ParentType, ContextType>;
  isfollowing?: Resolver<Maybe<ResolversTypes['Boolean']>, ParentType, ContextType>;
  slug?: Resolver<Maybe<ResolversTypes['Int']>, ParentType, ContextType>;
  status?: Resolver<Maybe<ResolversTypes['Int']>, ParentType, ContextType>;
  textposts?: Resolver<Maybe<Array<ResolversTypes['ProfilePostMedia']>>, ParentType, ContextType>;
  username?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  videoposts?: Resolver<Maybe<Array<ResolversTypes['ProfilePostMedia']>>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type ProfileInfoResolvers<ContextType = any, ParentType extends ResolversParentTypes['ProfileInfo'] = ResolversParentTypes['ProfileInfo']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<ResolversTypes['Profile']>, ParentType, ContextType>;
  status?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type ProfilePostMediaResolvers<ContextType = any, ParentType extends ResolversParentTypes['ProfilePostMedia'] = ResolversParentTypes['ProfilePostMedia']> = ResolversObject<{
  contenttype?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  createdat?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  id?: Resolver<ResolversTypes['ID'], ParentType, ContextType>;
  media?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  title?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type ProfileUserResolvers<ContextType = any, ParentType extends ResolversParentTypes['ProfileUser'] = ResolversParentTypes['ProfileUser']> = ResolversObject<{
  id?: Resolver<ResolversTypes['ID'], ParentType, ContextType>;
  img?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  isfollowed?: Resolver<Maybe<ResolversTypes['Boolean']>, ParentType, ContextType>;
  isfollowing?: Resolver<Maybe<ResolversTypes['Boolean']>, ParentType, ContextType>;
  slug?: Resolver<Maybe<ResolversTypes['Int']>, ParentType, ContextType>;
  username?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type QueryResolvers<ContextType = any, ParentType extends ResolversParentTypes['Query'] = ResolversParentTypes['Query']> = ResolversObject<{
  balance?: Resolver<ResolversTypes['CurrentLiquidity'], ParentType, ContextType>;
  getActionPrices?: Resolver<ResolversTypes['GetActionPricesResponse'], ParentType, ContextType>;
  getChat?: Resolver<ResolversTypes['ChatResponse'], ParentType, ContextType, RequireFields<QueryGetChatArgs, 'chatid'>>;
  getCommentInfo?: Resolver<ResolversTypes['CommentInfoResponse'], ParentType, ContextType, RequireFields<QueryGetCommentInfoArgs, 'commentId'>>;
  getDailyFreeStatus?: Resolver<ResolversTypes['GetDailyResponse'], ParentType, ContextType>;
  getPostInfo?: Resolver<ResolversTypes['PostInfoResponse'], ParentType, ContextType, RequireFields<QueryGetPostInfoArgs, 'postid'>>;
  getProfile?: Resolver<ResolversTypes['ProfileInfo'], ParentType, ContextType, Partial<QueryGetProfileArgs>>;
  getReferralInfo?: Resolver<ResolversTypes['ReferralInfoResponse'], ParentType, ContextType>;
  getUserInfo?: Resolver<ResolversTypes['UserInfoResponse'], ParentType, ContextType>;
  hello?: Resolver<ResolversTypes['HelloResponse'], ParentType, ContextType>;
  listBlockedUsers?: Resolver<ResolversTypes['BlockedUsersResponse'], ParentType, ContextType, Partial<QueryListBlockedUsersArgs>>;
  listChatMessages?: Resolver<ResolversTypes['AddChatmessageResponse'], ParentType, ContextType, RequireFields<QueryListChatMessagesArgs, 'chatid'>>;
  listChats?: Resolver<ResolversTypes['ChatResponse'], ParentType, ContextType, Partial<QueryListChatsArgs>>;
  listChildComments?: Resolver<ResolversTypes['CommentResponse'], ParentType, ContextType, RequireFields<QueryListChildCommentsArgs, 'parent'>>;
  listFollowRelations?: Resolver<ResolversTypes['FollowRelationsResponse'], ParentType, ContextType, Partial<QueryListFollowRelationsArgs>>;
  listFriends?: Resolver<ResolversTypes['UserFriendsResponse'], ParentType, ContextType, Partial<QueryListFriendsArgs>>;
  listPaymentLogs?: Resolver<ResolversTypes['UserLogWins'], ParentType, ContextType, RequireFields<QueryListPaymentLogsArgs, 'day'>>;
  listPosts?: Resolver<ResolversTypes['PostListResponse'], ParentType, ContextType, Partial<QueryListPostsArgs>>;
  listTags?: Resolver<ResolversTypes['TagSearchResponse'], ParentType, ContextType, Partial<QueryListTagsArgs>>;
  listTodaysInteractions?: Resolver<ResolversTypes['ListTodaysInteractionsResponse'], ParentType, ContextType>;
  listUsers?: Resolver<ResolversTypes['UserListResponse'], ParentType, ContextType, Partial<QueryListUsersArgs>>;
  listWinLogs?: Resolver<ResolversTypes['UserLogWins'], ParentType, ContextType, RequireFields<QueryListWinLogsArgs, 'day'>>;
  referralList?: Resolver<ResolversTypes['ReferralListResponse'], ParentType, ContextType, Partial<QueryReferralListArgs>>;
  searchTags?: Resolver<ResolversTypes['TagSearchResponse'], ParentType, ContextType, RequireFields<QuerySearchTagsArgs, 'tagName'>>;
  searchUser?: Resolver<ResolversTypes['UserListResponse'], ParentType, ContextType, Partial<QuerySearchUserArgs>>;
}>;

export type ReferralInfoResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['ReferralInfoResponse'] = ResolversParentTypes['ReferralInfoResponse']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  referralLink?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  referralUuid?: Resolver<Maybe<ResolversTypes['ID']>, ParentType, ContextType>;
  status?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type ReferralListResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['ReferralListResponse'] = ResolversParentTypes['ReferralListResponse']> = ResolversObject<{
  ResponseCode?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  affectedRows?: Resolver<ResolversTypes['ReferralUsers'], ParentType, ContextType>;
  counter?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type ReferralUsersResolvers<ContextType = any, ParentType extends ResolversParentTypes['ReferralUsers'] = ResolversParentTypes['ReferralUsers']> = ResolversObject<{
  iInvited?: Resolver<Array<ResolversTypes['ProfileUser']>, ParentType, ContextType>;
  invitedBy?: Resolver<Maybe<ResolversTypes['ProfileUser']>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type SubscriptionResolvers<ContextType = any, ParentType extends ResolversParentTypes['Subscription'] = ResolversParentTypes['Subscription']> = ResolversObject<{
  getBtcTokenPrice?: SubscriptionResolver<Maybe<ResolversTypes['String']>, "getBtcTokenPrice", ParentType, ContextType>;
  getChatMessages?: SubscriptionResolver<ResolversTypes['AddChatmessageResponse'], "getChatMessages", ParentType, ContextType, RequireFields<SubscriptionGetChatMessagesArgs, 'chatid'>>;
  numberIncremented?: SubscriptionResolver<Maybe<ResolversTypes['Int']>, "numberIncremented", ParentType, ContextType>;
  setChatMessages?: SubscriptionResolver<ResolversTypes['AddChatmessageResponse'], "setChatMessages", ParentType, ContextType, RequireFields<SubscriptionSetChatMessagesArgs, 'chatid' | 'content'>>;
}>;

export type TagResolvers<ContextType = any, ParentType extends ResolversParentTypes['Tag'] = ResolversParentTypes['Tag']> = ResolversObject<{
  name?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type TagSearchResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['TagSearchResponse'] = ResolversParentTypes['TagSearchResponse']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<Array<Maybe<ResolversTypes['Tag']>>>, ParentType, ContextType>;
  counter?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type TodaysInteractionsDataResolvers<ContextType = any, ParentType extends ResolversParentTypes['TodaysInteractionsData'] = ResolversParentTypes['TodaysInteractionsData']> = ResolversObject<{
  totalDetails?: Resolver<Maybe<ResolversTypes['TodaysInteractionsDetailsData']>, ParentType, ContextType>;
  totalInteractions?: Resolver<Maybe<ResolversTypes['Decimal']>, ParentType, ContextType>;
  totalScore?: Resolver<Maybe<ResolversTypes['Decimal']>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type TodaysInteractionsDetailsDataResolvers<ContextType = any, ParentType extends ResolversParentTypes['TodaysInteractionsDetailsData'] = ResolversParentTypes['TodaysInteractionsDetailsData']> = ResolversObject<{
  comments?: Resolver<Maybe<ResolversTypes['Decimal']>, ParentType, ContextType>;
  commentsScore?: Resolver<Maybe<ResolversTypes['Decimal']>, ParentType, ContextType>;
  dislikes?: Resolver<Maybe<ResolversTypes['Decimal']>, ParentType, ContextType>;
  dislikesScore?: Resolver<Maybe<ResolversTypes['Decimal']>, ParentType, ContextType>;
  likes?: Resolver<Maybe<ResolversTypes['Decimal']>, ParentType, ContextType>;
  likesScore?: Resolver<Maybe<ResolversTypes['Decimal']>, ParentType, ContextType>;
  views?: Resolver<Maybe<ResolversTypes['Decimal']>, ParentType, ContextType>;
  viewsScore?: Resolver<Maybe<ResolversTypes['Decimal']>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type UserResolvers<ContextType = any, ParentType extends ResolversParentTypes['User'] = ResolversParentTypes['User']> = ResolversObject<{
  biography?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  createdat?: Resolver<Maybe<ResolversTypes['Date']>, ParentType, ContextType>;
  id?: Resolver<Maybe<ResolversTypes['ID']>, ParentType, ContextType>;
  img?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  slug?: Resolver<Maybe<ResolversTypes['Int']>, ParentType, ContextType>;
  status?: Resolver<Maybe<ResolversTypes['Int']>, ParentType, ContextType>;
  updatedat?: Resolver<Maybe<ResolversTypes['Date']>, ParentType, ContextType>;
  username?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type UserFriendsResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['UserFriendsResponse'] = ResolversParentTypes['UserFriendsResponse']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<Array<Maybe<ResolversTypes['Userinfo']>>>, ParentType, ContextType>;
  counter?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type UserInfoResolvers<ContextType = any, ParentType extends ResolversParentTypes['UserInfo'] = ResolversParentTypes['UserInfo']> = ResolversObject<{
  amountblocked?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  amountfollowed?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  amountfollower?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  amountfriends?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  amountposts?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  invited?: Resolver<ResolversTypes['ID'], ParentType, ContextType>;
  liquidity?: Resolver<ResolversTypes['Decimal'], ParentType, ContextType>;
  updatedat?: Resolver<Maybe<ResolversTypes['Date']>, ParentType, ContextType>;
  userid?: Resolver<ResolversTypes['ID'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type UserInfoResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['UserInfoResponse'] = ResolversParentTypes['UserInfoResponse']> = ResolversObject<{
  ResponseCode?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<ResolversTypes['UserInfo']>, ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type UserListResponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['UserListResponse'] = ResolversParentTypes['UserListResponse']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<Array<Maybe<ResolversTypes['User']>>>, ParentType, ContextType>;
  counter?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type UserLogWinsResolvers<ContextType = any, ParentType extends ResolversParentTypes['UserLogWins'] = ResolversParentTypes['UserLogWins']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<Array<Maybe<ResolversTypes['LogWins']>>>, ParentType, ContextType>;
  counter?: Resolver<ResolversTypes['Int'], ParentType, ContextType>;
  status?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type UserinfoResolvers<ContextType = any, ParentType extends ResolversParentTypes['Userinfo'] = ResolversParentTypes['Userinfo']> = ResolversObject<{
  biography?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  img?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  slug?: Resolver<Maybe<ResolversTypes['Int']>, ParentType, ContextType>;
  updatedat?: Resolver<ResolversTypes['Date'], ParentType, ContextType>;
  userid?: Resolver<Maybe<ResolversTypes['ID']>, ParentType, ContextType>;
  username?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type UserinforesponseResolvers<ContextType = any, ParentType extends ResolversParentTypes['Userinforesponse'] = ResolversParentTypes['Userinforesponse']> = ResolversObject<{
  ResponseCode?: Resolver<Maybe<ResolversTypes['String']>, ParentType, ContextType>;
  affectedRows?: Resolver<Maybe<ResolversTypes['Userinfo']>, ParentType, ContextType>;
  status?: Resolver<ResolversTypes['String'], ParentType, ContextType>;
  __isTypeOf?: IsTypeOfResolverFn<ParentType, ContextType>;
}>;

export type Resolvers<ContextType = any> = ResolversObject<{
  ActionPriceResult?: ActionPriceResultResolvers<ContextType>;
  AddChatResponse?: AddChatResponseResolvers<ContextType>;
  AddChatmessageResponse?: AddChatmessageResponseResolvers<ContextType>;
  AddPostResponse?: AddPostResponseResolvers<ContextType>;
  BlockedUser?: BlockedUserResolvers<ContextType>;
  BlockedUsers?: BlockedUsersResolvers<ContextType>;
  BlockedUsersResponse?: BlockedUsersResponseResolvers<ContextType>;
  BtcTokenPriceResponse?: BtcTokenPriceResponseResolvers<ContextType>;
  Chat?: ChatResolvers<ContextType>;
  ChatMessage?: ChatMessageResolvers<ContextType>;
  ChatMessageInfo?: ChatMessageInfoResolvers<ContextType>;
  ChatParticipant?: ChatParticipantResolvers<ContextType>;
  ChatResponse?: ChatResponseResolvers<ContextType>;
  Chatinfo?: ChatinfoResolvers<ContextType>;
  Comment?: CommentResolvers<ContextType>;
  CommentInfo?: CommentInfoResolvers<ContextType>;
  CommentInfoResponse?: CommentInfoResponseResolvers<ContextType>;
  CommentResponse?: CommentResponseResolvers<ContextType>;
  CurrentLiquidity?: CurrentLiquidityResolvers<ContextType>;
  DailyFreeResponse?: DailyFreeResponseResolvers<ContextType>;
  Date?: GraphQLScalarType;
  Decimal?: GraphQLScalarType;
  DefaultResponse?: DefaultResponseResolvers<ContextType>;
  FollowRelations?: FollowRelationsResolvers<ContextType>;
  FollowRelationsResponse?: FollowRelationsResponseResolvers<ContextType>;
  FollowStatusResponse?: FollowStatusResponseResolvers<ContextType>;
  GetActionPricesResponse?: GetActionPricesResponseResolvers<ContextType>;
  GetDailyResponse?: GetDailyResponseResolvers<ContextType>;
  HelloResponse?: HelloResponseResolvers<ContextType>;
  ListTodaysInteractionsResponse?: ListTodaysInteractionsResponseResolvers<ContextType>;
  LogWins?: LogWinsResolvers<ContextType>;
  Mutation?: MutationResolvers<ContextType>;
  Post?: PostResolvers<ContextType>;
  PostInfo?: PostInfoResolvers<ContextType>;
  PostInfoResponse?: PostInfoResponseResolvers<ContextType>;
  PostListResponse?: PostListResponseResolvers<ContextType>;
  PostResponse?: PostResponseResolvers<ContextType>;
  Postinfo?: PostinfoResolvers<ContextType>;
  Profile?: ProfileResolvers<ContextType>;
  ProfileInfo?: ProfileInfoResolvers<ContextType>;
  ProfilePostMedia?: ProfilePostMediaResolvers<ContextType>;
  ProfileUser?: ProfileUserResolvers<ContextType>;
  Query?: QueryResolvers<ContextType>;
  ReferralInfoResponse?: ReferralInfoResponseResolvers<ContextType>;
  ReferralListResponse?: ReferralListResponseResolvers<ContextType>;
  ReferralUsers?: ReferralUsersResolvers<ContextType>;
  Subscription?: SubscriptionResolvers<ContextType>;
  Tag?: TagResolvers<ContextType>;
  TagSearchResponse?: TagSearchResponseResolvers<ContextType>;
  TodaysInteractionsData?: TodaysInteractionsDataResolvers<ContextType>;
  TodaysInteractionsDetailsData?: TodaysInteractionsDetailsDataResolvers<ContextType>;
  User?: UserResolvers<ContextType>;
  UserFriendsResponse?: UserFriendsResponseResolvers<ContextType>;
  UserInfo?: UserInfoResolvers<ContextType>;
  UserInfoResponse?: UserInfoResponseResolvers<ContextType>;
  UserListResponse?: UserListResponseResolvers<ContextType>;
  UserLogWins?: UserLogWinsResolvers<ContextType>;
  Userinfo?: UserinfoResolvers<ContextType>;
  Userinforesponse?: UserinforesponseResolvers<ContextType>;
}>;

