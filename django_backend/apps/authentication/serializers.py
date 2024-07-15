from django.contrib.auth import get_user_model
from django.contrib.auth.hashers import make_password
from django.contrib.auth.models import User
from rest_framework import serializers

UserModel = get_user_model()


class UserRegistrationSerializer(serializers.ModelSerializer):
    password = serializers.CharField(write_only=True)

    class Meta:
        model = User
        fields = ('username', 'password', 'email')

    def create(self, validated_data):
        email = validated_data.pop('email', 'default@email.com')
        user = User.objects.create_user(
            username=validated_data['username'],
            email=email,
            password=validated_data['password']
        )
        return user
