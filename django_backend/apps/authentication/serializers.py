from rest_framework import serializers
from django.contrib.auth import get_user_model
from django.contrib.auth.hashers import make_password

User = get_user_model()


class CustomUserSerializer(serializers.ModelSerializer):
    saldo = serializers.DecimalField(max_digits=10, decimal_places=2, default=0)
    status = serializers.CharField(max_length=20, default='active')

    class Meta:
        model = User
        fields = ('id', 'email', 'username', 'saldo', 'status', 'password')
        extra_kwargs = {
            'password': {'write_only': True},
        }

    def create(self, validated_data):
        # Extract default value from validated_data
        password = validated_data.pop('password', None)
        saldo = validated_data.pop('saldo', 0)
        status = validated_data.pop('status', 'active')
        email = validated_data.pop('email', 'default@email.com')

        # Hash the password before saving
        validated_data['password'] = make_password(password)

        # Create the user using the custom manager's create_user method
        user = User.objects.create_user(email=email, username=validated_data['username'], password=password, saldo=saldo, status=status)
        print(user)

        return user
